const csv_input = document.querySelector("#csv_input");
const alert_close = document.querySelectorAll(".alert_close");

csv_input.addEventListener("change", () => {
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

// WebSocket configuration for real-time updates
import Echo from 'laravel-echo';

window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});

// Listen for CSV upload events
window.Echo.channel('csv-uploads')
    .listen('CsvUploadProgress', (e) => {
        console.log('Progress:', e);
        const statusElement = document.getElementById(`job-id-${e.batchId}`);
        if (statusElement) {
            statusElement.textContent = `Processing: ${e.progress}%`;
        }
    })
    .listen('CsvUploadFinished', (e) => {
        console.log('Finished:', e);
        const statusElement = document.getElementById(`job-id-${e.batchId}`);
        if (statusElement) {
            statusElement.textContent = 'Completed';
            statusElement.className = 'text-green-600';
        }
    })
    .listen('CsvUploadFailed', (e) => {
        console.log('Failed:', e);
        const statusElement = document.getElementById(`job-id-${e.batchId}`);
        if (statusElement) {
            statusElement.textContent = 'Failed';
            statusElement.className = 'text-red-600';
        }
    });
