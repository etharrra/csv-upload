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
