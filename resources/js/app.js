import "./bootstrap";

// File input change handler
const csv_input = document.querySelector("#csv_input");
const alert_close = document.querySelectorAll(".alert_close");
const uploadForm = csv_input?.closest("form");
const uploadButton = uploadForm?.querySelector('button[type="submit"]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
let uploadInProgress = false;

csv_input?.addEventListener("change", () => {
    if (csv_input.files.length) {
        let file_name = csv_input.files[0].name;
        document.querySelector(".upload-container p").innerHTML = file_name;
    }
});

uploadForm?.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (uploadInProgress) {
        return;
    }

    const file = csv_input?.files?.[0];
    let upload = null;

    if (!file) {
        showUploadAlert("Please choose a CSV file first.", "error");
        return;
    }

    uploadInProgress = true;
    setUploadState(true, "Preparing...");

    try {
        upload = await requestPresignedUpload(file);

        setUploadState(true, "Uploading...");
        await uploadToS3(upload, file);

        upsertPendingFileRow(upload.file);
        showUploadAlert("File uploaded. Processing will start after AWS confirms the upload.", "success");
        uploadForm.reset();
        document.querySelector(".upload-container p").innerHTML = "Click or drop file here";
    } catch (error) {
        console.error("Upload failed:", error);
        await cancelPendingUpload(upload);
        showUploadAlert(error.message || "Upload failed. Please try again.", "error");
    } finally {
        uploadInProgress = false;
        setUploadState(false, "Upload");
    }
});

alert_close.forEach((element) => {
    element.addEventListener("click", (event) => {
        let alerts = document.querySelectorAll("div[role=alert]");
        alerts.forEach((alert) => {
            alert.style.display = "none";
        });
    });
});

// Listen for new file uploads
window.Echo.channel("csv-uploads")
    .subscribed(() => {
        console.log("✅ CSV Uploads Channel Subscribed!");
    })
    .listen(".file.uploaded", (event) => {
        console.log("🆕 New file uploaded event received:", event);
        addNewFileToTable(event);
    });

// WebSocket channels for progress updates
const progress_channel = window.Echo.channel("public.csv-upload-progress.1");
const finish_channel = window.Echo.channel("public.csv-upload-finished.1");
const failed_channel = window.Echo.channel("public.csv-upload-failed.1");

progress_channel
    .subscribed(() => {
        console.log("✅ Progress Channel Subscribed!");
    })
    .listen(".csv-upload-progress", (event) => {
        console.log("📊 Progress event:", event);
        UpdateProgress(event);
    });

finish_channel
    .subscribed(() => {
        console.log("✅ Finish Channel Subscribed!");
    })
    .listen(".csv-upload-finished", (event) => {
        console.log("✅ Finished event:", event);
        finishProgress(event);
    });

failed_channel
    .subscribed(() => {
        console.log("✅ Failed Channel Subscribed!");
    })
    .listen(".csv-upload-failed", (event) => {
        console.log("❌ Failed event:", event);
        failedProgress(event);
    });

function addNewFileToTable(event) {
    console.log("🔧 addNewFileToTable called with:", event);
    const tbody = document.querySelector("tbody");

    if (!tbody) {
        console.error("❌ tbody not found!");
        return;
    }

    const noFilesRow = tbody.querySelector('td[colspan="3"]');

    // Only remove "No files" row if it exists
    if (noFilesRow) {
        console.log("🗑️ Removing 'No files' row");
        noFilesRow.closest("tr").remove();
    }

    const pendingCell = document.getElementById(`file-id-${event.fileId}`);
    if (pendingCell) {
        pendingCell.id = `job-id-${event.batchId}`;
        pendingCell.innerHTML = event.status;
        pendingCell.className = "";
        replayPendingBatchEvent(event.batchId);
        return; // File already in table
    }

    const existingRow = document.getElementById(`job-id-${event.batchId}`);
    if (existingRow) {
        console.log("⚠️ File already in table, skipping");
        return;
    }

    const tr = document.createElement("tr");
    const uploadedAt = new Date(event.uploadedAt);
    const formattedDate = uploadedAt.toLocaleString("en-US", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
    });

    tr.innerHTML = `
        <td>
            ${formattedDate} <br>
            just now
        </td>
        <td>${event.fileName}</td>
        <td id="job-id-${event.batchId}">${event.status}</td>
    `;

    // Add to the top of the table
    tbody.insertBefore(tr, tbody.firstChild);
    console.log("✅ Row added to table for batch:", event.batchId);

    replayPendingBatchEvent(event.batchId);
}

async function requestPresignedUpload(file) {
    const response = await fetch(uploadForm.dataset.presignUrl, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
            name: file.name,
            size: file.size,
            type: file.type || "text/csv",
        }),
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        const validationMessage = Object.values(error.errors ?? {})
            .flat()
            .shift();

        throw new Error(validationMessage || error.message || "Could not prepare the upload.");
    }

    return response.json();
}

async function uploadToS3(upload, file) {
    const response = await fetch(upload.url, {
        method: "PUT",
        headers: upload.headers ?? {},
        body: file,
    });

    if (!response.ok) {
        throw new Error("The direct upload to S3 failed.");
    }
}

async function cancelPendingUpload(upload) {
    if (!upload?.fileId || !uploadForm.dataset.cancelUrlTemplate) {
        return;
    }

    const url = uploadForm.dataset.cancelUrlTemplate.replace("__FILE_ID__", upload.fileId);

    await fetch(url, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
    }).catch((error) => {
        console.warn("Could not cancel pending upload:", error);
    });
}

function upsertPendingFileRow(file) {
    const tbody = document.querySelector("tbody");

    if (!tbody || document.getElementById(`file-id-${file.id}`)) {
        return;
    }

    const noFilesRow = tbody.querySelector('td[colspan="3"]');

    if (noFilesRow) {
        noFilesRow.closest("tr").remove();
    }

    const tr = document.createElement("tr");
    const uploadedAt = new Date(file.createdAt);
    const formattedDate = uploadedAt.toLocaleString("en-US", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
    });

    tr.innerHTML = `
        <td>
            ${formattedDate} <br>
            just now
        </td>
        <td>${file.name}</td>
        <td id="file-id-${file.id}">${file.status}</td>
    `;

    tbody.insertBefore(tr, tbody.firstChild);
}

function replayPendingBatchEvent(batchId) {
    if (window._pendingBatchEvents?.[batchId]) {
        const pending = window._pendingBatchEvents[batchId];
        console.log("🔄 Replaying buffered event:", pending.type);
        if (pending.type === "progress") UpdateProgress(pending.event);
        if (pending.type === "finished") finishProgress(pending.event);
        if (pending.type === "failed") failedProgress(pending.event);
        delete window._pendingBatchEvents[batchId];
    }
}

function setUploadState(disabled, label) {
    if (!uploadButton) {
        return;
    }

    uploadButton.disabled = disabled;
    uploadButton.textContent = label;
}

function showUploadAlert(message, type) {
    const existingAlerts = document.querySelectorAll("div[role=alert]");
    existingAlerts.forEach((alert) => alert.remove());

    const alert = document.createElement("div");
    const styles =
        type === "success"
            ? "bg-green-50 border border-green-400 text-green-600"
            : "bg-red-50 border border-red-300 text-red-600";

    alert.className = `${styles} px-5 py-3 rounded relative mb-3`;
    alert.setAttribute("role", "alert");
    alert.innerHTML = `<strong class="font-bold">${message}</strong>`;

    document.querySelector(".max-w-7xl")?.prepend(alert);
}

function bufferEvent(batchId, type, event) {
    console.log(`📦 Buffering ${type} event for batch:`, batchId);
    window._pendingBatchEvents = window._pendingBatchEvents ?? {};
    window._pendingBatchEvents[batchId] = { type, event };
}

function UpdateProgress(event) {
    let td = window.document.querySelector(`#job-id-${event.batch.batchId}`);
    if (td) {
        console.log(
            `📊 Updating progress for batch ${event.batch.batchId}: ${event.batch.progress}%`,
        );
        td.innerHTML = `Processing (${event.batch.progress}%)`;
    } else {
        console.log(
            `⚠️ Row not found for batch ${event.batch.batchId}, buffering progress event`,
        );
        bufferEvent(event.batch.batchId, "progress", event);
    }
}

function finishProgress(event) {
    let td = window.document.querySelector(`#job-id-${event.batch.batchId}`);
    if (td) {
        console.log(`✅ Marking batch ${event.batch.batchId} as completed`);
        td.innerHTML = `Completed`;
        td.className = "text-green-600";
    } else {
        console.log(
            `⚠️ Row not found for batch ${event.batch.batchId}, buffering finished event`,
        );
        bufferEvent(event.batch.batchId, "finished", event);
    }
}

function failedProgress(event) {
    let td = window.document.querySelector(`#job-id-${event.batch.batchId}`);
    if (td) {
        console.log(`❌ Marking batch ${event.batch.batchId} as failed`);
        td.innerHTML = `Failed`;
        td.className = "text-red-600";
    } else {
        console.log(
            `⚠️ Row not found for batch ${event.batch.batchId}, buffering failed event`,
        );
        bufferEvent(event.batch.batchId, "failed", event);
    }
}
