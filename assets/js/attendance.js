
document.addEventListener("DOMContentLoaded", function () {

    const qrContainers = document.querySelectorAll(
        "[data-qr-url]"
    );

    qrContainers.forEach(function (container) {

        const relativeUrl = container.dataset.qrUrl;

        if (!relativeUrl) {
            return;
        }

        // Build a complete URL for scanning on phones.
        const fullUrl = new URL(
            relativeUrl,
            window.location.origin
        ).href;

        if (typeof QRCode === "undefined") {
            container.textContent = "QR library unavailable.";
            return;
        }

        container.innerHTML = "";

        new QRCode(container, {
            text: fullUrl,
            width: 170,
            height: 170,
            colorDark: "#111827",
            colorLight: "#FFFFFF",
            correctLevel: QRCode.CorrectLevel.M
        });

    });

    // Restrict student code input to six digits.
    const codeInput = document.getElementById(
        "attendance_code"
    );

    if (codeInput) {

        codeInput.addEventListener("input", function () {

            codeInput.value = codeInput.value
                .replace(/\D/g, "")
                .slice(0, 6);

        });
    }

});
