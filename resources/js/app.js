import "./bootstrap";

// File input change handler
const csv_input = document.querySelector("#csv_input");
const alert_close = document.querySelectorAll(".alert_close");

csv_input?.addEventListener("change", () => {
    if (csv_input.files.length) {
        let file_name = csv_input.files[0].name;
        document.querySelector(".upload-container p").innerHTML = file_name;
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

    // Check if file already exists to avoid duplicates
    const existingRow = document.getElementById(`job-id-${event.batchId}`);
    if (existingRow) {
        console.log("⚠️ File already in table, skipping");
        return; // File already in table
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

    // Replay any buffered events for this batch that arrived before the row existed
    if (window._pendingBatchEvents?.[event.batchId]) {
        const pending = window._pendingBatchEvents[event.batchId];
        console.log("🔄 Replaying buffered event:", pending.type);
        if (pending.type === "progress") UpdateProgress(pending.event);
        if (pending.type === "finished") finishProgress(pending.event);
        if (pending.type === "failed") failedProgress(pending.event);
        delete window._pendingBatchEvents[event.batchId];
    }
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
