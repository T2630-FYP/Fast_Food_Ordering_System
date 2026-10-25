(function () {
    "use strict";
    const page = document.querySelector(".eo-contact-page");
    const openButton = document.getElementById("open-message-btn");
    const prompt = document.getElementById("message-prompt");
    const form = document.getElementById("contact-message-form");
    if (!page || !openButton || !prompt || !form) return;
    let focusTimer;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    function toggleContactForm(open, moveFocus) {
        clearTimeout(focusTimer);
        page.classList.toggle("expanded", open);
        openButton.setAttribute("aria-expanded", String(open));
        form.setAttribute("aria-hidden", String(!open));
        form.hidden = !open;
        prompt.hidden = open;
        if (!moveFocus) return;
        if (open) {
            form.scrollIntoView({behavior: reducedMotion.matches ? "instant" : "smooth", block: "center"});
            focusTimer = setTimeout(function () {
                if (!form.hidden) form.querySelector('input:not([type="hidden"])').focus({preventScroll: true});
            }, reducedMotion.matches ? 0 : 300);
        }
    }

    page.classList.add("contact-enhanced");
    toggleContactForm(page.dataset.startOpen === "1", false);
    openButton.addEventListener("click", function () { toggleContactForm(true, true); });

    function clearFieldError(field) {
        const id = "contact-error-" + field.name;
        const error = document.getElementById(id);
        if (error) error.hidden = true;
        field.removeAttribute("aria-invalid");
        if (field.getAttribute("aria-describedby") === id) field.removeAttribute("aria-describedby");
    }
    form.querySelectorAll(".form-field input, .form-field select, .form-field textarea").forEach(function (field) {
        field.addEventListener("input", function () { clearFieldError(field); });
        field.addEventListener("change", function () { clearFieldError(field); });
    });
})();
