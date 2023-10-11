import "./bootstrap";

const progress_channel = window.Echo.channel("public.csv-upload-progress.1");
const finish_channel = window.Echo.channel("public.csv-upload-finished.1");
const failed_channel = window.Echo.channel("public.csv-upload-failed.1");

progress_channel
    .subscribed(() => {
        console.log("Progress Channel Subscribed!");
    })
    .listen(".csv-upload-progress", (event) => {
        console.log(event.batch.batchId);
        UpdateProgress(event);
    });

finish_channel
    .subscribed(() => {
        console.log("Finish Channel Subscribed!");
    })
    .listen(".csv-upload-finished", (event) => {
        console.log(event.batch.batchId);
        finishProgress(event);
    });

failed_channel
    .subscribed(() => {
        console.log("Failed Channel Subscribed!");
    })
    .listen(".csv-upload-failed", (event) => {
        console.log(event.batch.batchId);
        failedProgress(event);
    });

function UpdateProgress(event) {
    let td = window.document.querySelector(`#job-id-${event.batch.batchId}`);
    let progress = event.batch.progress;
    td.innerHTML = `Processing (${progress}%)`;
}

function finishProgress(event) {
    let td = window.document.querySelector(`#job-id-${event.batch.batchId}`);
    td.innerHTML = `Completed`;
}

function failedProgress(event) {
    let td = window.document.querySelector(`#job-id-${event.batch.batchId}`);
    td.innerHTML = `Failed`;
}
