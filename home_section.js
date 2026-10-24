(function () {
    "use strict";
    function initialiseSlider() {
        const slider = document.getElementById("eo-slider");
        const prev = document.querySelector(".eo-slide-prev");
        const next = document.querySelector(".eo-slide-next");
        const counter = document.getElementById("eo-counter");
        if (!slider || !prev || !next || !counter) return;
        const cards = Array.from(slider.querySelectorAll(".eo-food-card"));
        if (!cards.length) {
            prev.disabled = next.disabled = true;
            counter.textContent = "00 / 00";
            return;
        }
        const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
        let requestedIndex = null;
        let settleTimer;
        function getStep() {
            return cards.length < 2 ? 1 : Math.max(1, cards[1].offsetLeft - cards[0].offsetLeft);
        }
        function getMaxIndex() {
            return Math.max(0, Math.round((slider.scrollWidth - slider.clientWidth) / getStep()));
        }
        function getPosition() {
            return Math.max(0, Math.min(getMaxIndex(), Math.round(slider.scrollLeft / getStep())));
        }
        function updateControls() {
            const position = getPosition();
            const max = getMaxIndex();
            prev.disabled = position === 0;
            next.disabled = position >= max;
            const text = String(position + 1).padStart(2, "0") + " / " + String(max + 1).padStart(2, "0");
            if (counter.textContent.trim() !== text) counter.textContent = text;
        }
        function move(direction) {
            const target = Math.max(0, Math.min(getMaxIndex(), (requestedIndex ?? getPosition()) + direction));
            requestedIndex = target;
            slider.scrollTo({left: target * getStep(), behavior: reducedMotion.matches ? "auto" : "smooth"});
        }
        prev.addEventListener("click", () => move(-1));
        next.addEventListener("click", () => move(1));
        slider.addEventListener("scroll", function () {
            updateControls();
            clearTimeout(settleTimer);
            settleTimer = setTimeout(() => { requestedIndex = null; }, 160);
        }, {passive: true});
        slider.addEventListener("wheel", () => { requestedIndex = null; }, {passive: true});
        window.addEventListener("resize", function () {
            requestedIndex = null;
            slider.scrollTo({left: getPosition() * getStep(), behavior: "instant"});
            updateControls();
        });
        slider.addEventListener("keydown", function (event) {
            if (event.key === "ArrowRight" || event.key === "ArrowLeft") {
                event.preventDefault();
                move(event.key === "ArrowRight" ? 1 : -1);
            }
        });
        let mouseDown = false, isDragging = false, startX = 0, startScroll = 0, preventCardClick = false;
        slider.addEventListener("dragstart", event => event.preventDefault());
        slider.addEventListener("pointerdown", function (event) {
            if (event.pointerType !== "mouse" || event.button !== 0) return;
            mouseDown = true;
            isDragging = false;
            preventCardClick = false;
            requestedIndex = null;
            startX = event.clientX;
            startScroll = slider.scrollLeft;
        });
        window.addEventListener("pointermove", function (event) {
            if (!mouseDown) return;
            const distance = event.clientX - startX;
            if (Math.abs(distance) > 6) {
                isDragging = true;
                slider.classList.add("is-dragging");
                slider.scrollLeft = startScroll - distance;
            }
        });
        function finishDrag() {
            if (!mouseDown) return;
            mouseDown = false;
            slider.classList.remove("is-dragging");
            if (isDragging) {
                preventCardClick = true;
                slider.scrollTo({left: getPosition() * getStep(), behavior: reducedMotion.matches ? "auto" : "smooth"});
                setTimeout(() => { preventCardClick = false; }, 250);
            }
            isDragging = false;
        }
        window.addEventListener("pointerup", finishDrag);
        window.addEventListener("pointercancel", finishDrag);
        window.addEventListener("blur", finishDrag);
        slider.addEventListener("click", function (event) {
            if (preventCardClick) {
                event.preventDefault();
                event.stopPropagation();
                preventCardClick = false;
            }
        }, true);
        updateControls();
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialiseSlider);
    else initialiseSlider();
})();
