// Category image selection uses the same limits as the server-side upload handler.
(function () {
    "use strict";

    document.querySelectorAll("[data-category-image-editor]").forEach(function (editor) {
        const input = editor.querySelector("[data-category-image-input]");
        const preview = editor.querySelector("[data-category-image-preview]");
        if (!input || !preview) return;

        const originalSource = preview.getAttribute("src");
        let previewUrl = null;

        function clearPreview() {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
            preview.src = originalSource;
        }

        input.addEventListener("change", function () {
            clearPreview();
            const file = input.files && input.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024 || file.size === 0 ||
                !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
                !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                input.value = "";
                window.alert("Please choose a JPG, PNG or WebP image no larger than 5 MB.");
                return;
            }

            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
        });

        if (input.form) input.form.addEventListener("reset", clearPreview);
    });
})();
