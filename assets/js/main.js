
document.addEventListener("DOMContentLoaded", function () {

    const body = document.body;

    const toggle = document.querySelector(
        "[data-menu-toggle]"
    );

    const nav = document.querySelector(
        "[data-floating-nav]"
    );

    if (!toggle || !nav) {
        return;
    }

    const storageKey = "tuition-floating-nav-expanded";

    // Sidebar starts collapsed by default.
    let expanded = false;

    // Restore previous selection.
    try {
        expanded = localStorage.getItem(storageKey) === "true";
    } catch (error) {
        expanded = false;
    }

    function applyNavigation() {

        body.classList.toggle(
            "nav-expanded",
            expanded
        );

        toggle.setAttribute(
            "aria-expanded",
            String(expanded)
        );

        toggle.setAttribute(
            "aria-label",
            expanded
                ? "Collapse navigation"
                : "Expand navigation"
        );

        const icon = toggle.querySelector("span");

        if (icon) {
            icon.textContent = expanded ? "×" : "☰";
        }
    }

    // Expand or collapse when clicked.
    toggle.addEventListener("click", function () {

        expanded = !expanded;

        applyNavigation();

        try {
            localStorage.setItem(
                storageKey,
                String(expanded)
            );
        } catch (error) {
            // Navigation still works without storage.
        }

    });

    // Press Escape to collapse.
    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape" && expanded) {

            expanded = false;

            applyNavigation();

            try {
                localStorage.setItem(storageKey, "false");
            } catch (error) {
                // Ignore storage errors.
            }

            toggle.focus();
        }

    });

    applyNavigation();

});
