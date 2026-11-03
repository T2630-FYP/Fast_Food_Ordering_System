// EasyOrder customer features. Each section retains its own page checks.

// ===== WALLET UNLOCK: PIN ENTRY AND VISIBILITY =====
(function () {
    "use strict";
    const form = document.querySelector(".eo-wallet-unlock-form");
    if (!form) return;
    const field = form.querySelector('[name="wallet_pin"]');
    const eye = form.querySelector(".eo-wallet-unlock-eye");
    if (!field || !eye) return;

    field.addEventListener("input", () => {
        field.value = field.value.replace(/\D/g, "").slice(0, 6);
    });
    eye.hidden = false;
    eye.addEventListener("click", () => {
        const visible = field.type === "password";
        field.type = visible ? "text" : "password";
        eye.classList.toggle("is-visible", visible);
        eye.setAttribute("aria-pressed", String(visible));
        eye.setAttribute("aria-label", visible ? "Hide Wallet PIN" : "Show Wallet PIN");
    });
})();

// ===== WALLET ACTIVATION: PASSWORD VISIBILITY AND PIN CONFIRMATION =====
(function () {
    "use strict";
    const form = document.querySelector(".wallet-activation-form");
    if (!form) return;

    const pin = form.querySelector('[name="wallet_pin"]');
    const confirmation = form.querySelector('[name="confirm_pin"]');
    function validateConfirmation() {
        confirmation.setCustomValidity(confirmation.value && confirmation.value !== pin.value
            ? "The Wallet PIN confirmation does not match." : "");
    }

    form.querySelectorAll(".wallet-activation-visibility").forEach(button => {
        const input = document.getElementById(button.dataset.target);
        if (!input || !form.contains(input)) return;
        const label = button.getAttribute("aria-label").replace(/^Show /, "");
        button.hidden = false;
        button.addEventListener("click", () => {
            const reveal = input.type === "password";
            input.type = reveal ? "text" : "password";
            button.classList.toggle("is-visible", reveal);
            button.setAttribute("aria-pressed", String(reveal));
            button.setAttribute("aria-label", (reveal ? "Hide " : "Show ") + label);
        });
    });
    form.querySelectorAll(".wallet-activation-pin-input").forEach(input => {
        input.addEventListener("input", () => {
            input.value = input.value.replace(/\D/g, "").slice(0, 6);
            validateConfirmation();
        });
    });
    form.addEventListener("reset", () => {
        confirmation.setCustomValidity("");
        form.querySelectorAll(".wallet-activation-visibility").forEach(button => {
            if (button.getAttribute("aria-pressed") === "true") button.click();
        });
    });
    validateConfirmation();
})();

// ===== WALLET PIN RECOVERY: VISIBILITY AND RETRY =====
(function () {
    "use strict";
    const form = document.querySelector("[data-wallet-pin-recovery]");
    if (!form) return;
    const password = form.querySelector('[name="account_password"]');
    const pins = Array.from(form.querySelectorAll('.eo-wallet-pin-wrap input'));
    const feedback = document.getElementById("wallet-pin-feedback");
    const token = form.querySelector('[name="wallet_recovery_token"]');
    const submit = form.querySelector('[name="change_wallet_pin"]');
    const eyes = Array.from(form.querySelectorAll('.wallet-activation-visibility'));
    if (!password || pins.length !== 2 || !feedback || !token || !submit) return;

    eyes.forEach(eye => {
        const input = document.getElementById(eye.dataset.target);
        if (!input || !pins.includes(input)) return;
        const label = eye.getAttribute("aria-label").replace(/^Show /, "");
        eye.hidden = false;
        eye.addEventListener("click", () => {
            const visible = input.type === "password";
            input.type = visible ? "text" : "password";
            eye.classList.toggle("is-visible", visible);
            eye.setAttribute("aria-pressed", String(visible));
            eye.setAttribute("aria-label", (visible ? "Hide " : "Show ") + label);
        });
    });

    function clearPins() {
        pins.forEach(input => { input.value = ""; input.type = "password"; });
        eyes.forEach(eye => {
            eye.classList.remove("is-visible");
            eye.setAttribute("aria-pressed", "false");
            eye.setAttribute("aria-label", eye.getAttribute("aria-label").replace(/^Hide /, "Show "));
        });
    }
    if (!window.fetch || !window.FormData) return;
    let pending = false;
    form.addEventListener("submit", async event => {
        event.preventDefault();
        if (pending) return;
        const body = new FormData(form);
        body.set(submit.name, submit.value);
        pending = true;
        form.setAttribute("aria-busy", "true");
        submit.disabled = true;
        [password, ...pins].forEach(input => { input.readOnly = true; });
        eyes.forEach(eye => { eye.disabled = true; });
        try {
            const response = await fetch(form.action, {
                method: "POST", body, credentials: "same-origin",
                headers: {Accept: "application/json"}
            });
            if (response.redirected) {
                window.location.assign(response.url);
                return;
            }
            const result = await response.json();
            if (result.ok === true && result.redirect === "wallet.php") {
                password.value = "";
                clearPins();
                window.location.assign(new URL("wallet.php", form.action).href);
                return;
            }
            if (!result.token || typeof result.message !== "string") throw new Error("Invalid response");
            token.value = result.token;
            // Keep the already typed password only in this page's input after
            // the server verifies it and rejects an unchanged PIN.
            if (result.code !== "same_pin") password.value = "";
            clearPins();
            feedback.textContent = result.message;
            feedback.hidden = false;
            feedback.scrollIntoView({block: "nearest"});
            (result.code === "same_pin" ? pins[0] : password).focus({preventScroll: true});
        } catch (error) {
            password.value = "";
            clearPins();
            feedback.textContent = "Unable to confirm the PIN change. Reload this page before trying again.";
            feedback.hidden = false;
            feedback.focus();
        } finally {
            pending = false;
            form.removeAttribute("aria-busy");
            submit.disabled = false;
            [password, ...pins].forEach(input => { input.readOnly = false; });
            eyes.forEach(eye => { eye.disabled = false; });
        }
    });
})();

// ===== CART: UPDATE QUANTITIES AND REMOVE ITEMS IN PLACE =====
(function () {
    "use strict";
    const root = document.querySelector(".eo-cart-page");
    if (!root) return;
    const canUpdate = Boolean(window.fetch && window.FormData && window.DOMParser && document.querySelector("[data-cart-toast]"));
    const queue = new Map();
    let busy = false;
    let uncertain = false;
    let lastProductId = null;

    function forms() { return Array.from(root.querySelectorAll(".eo-cart-qty-form")); }
    function productId(form) { return form.querySelector('[name="product_id"]').value; }
    function findForm(id) { return forms().find(form => productId(form) === id); }
    function setBounds(form) {
        const input = form.querySelector('[name="qty"]');
        form.querySelectorAll("[data-eo-delta]").forEach(button => {
            button.hidden = false;
            button.disabled = uncertain || (Number(button.dataset.eoDelta) < 0 ? Number(input.value) <= Number(input.min) : Number(input.value) >= Number(input.max));
        });
    }
    function enhance() {
        forms().forEach(form => {
            if (typeof form.requestSubmit !== "function") return;
            const id = productId(form), input = form.querySelector('[name="qty"]');
            if (queue.has(id)) {
                const desired = Math.max(Number(input.min), Math.min(Number(input.max), queue.get(id)));
                if (desired === Number(input.defaultValue)) queue.delete(id);
                else queue.set(id, desired);
                input.value = String(desired);
            }
            setBounds(form);
            form.querySelector('[name="updatebtn"]').hidden = true;
        });
        // A product removed by stock validation must never be re-added by a queued update.
        for (const id of queue.keys()) if (!findForm(id)) queue.delete(id);
    }
    function lockOtherActions(locked) {
        root.querySelectorAll('button[name="clearbtn"],button[name="removebtn"],button[name="remove_rewardbtn"]').forEach(button => { button.disabled = locked; });
        const checkout = root.querySelector("a.eo-cart-checkout:not(.eo-cart-browse)");
        if (checkout) {
            if (locked) checkout.setAttribute("aria-disabled", "true");
            else checkout.removeAttribute("aria-disabled");
        }
    }
    function notify(kind, title, message, origin, signIn = false, action = null) {
        document.dispatchEvent(new CustomEvent("eo-cart-feedback", {detail: {kind, title, message, origin, signIn,
            showAction: Boolean(action), actionHref: action?.href, actionLabel: action?.label}}));
    }
    function readFeedback(page, updated) {
        const notice = page.querySelector("[data-cart-initial]");
        const errors = Array.from(updated.querySelectorAll(".form-message-error,.form-message-info"));
        if (errors.length > 0) {
            queue.clear();
            return {kind: "error", title: "Cart updated", message: errors.map(node => node.textContent.trim()).join(" ")};
        }
        if (!notice) throw new Error("Missing cart feedback");
        const link = notice.querySelector("[data-cart-toast-action]");
        return {kind: "success", title: notice.querySelector("[data-cart-toast-title]").textContent,
            message: notice.querySelector("[data-cart-toast-message]").textContent,
            action: notice.hasAttribute("data-cart-no-action") ? null : {href: link.getAttribute("href"), label: link.textContent}};
    }
    function applyCart(updated) {
        const x = window.scrollX, y = window.scrollY;
        const active = document.activeElement;
        const focusedForm = active && active.closest(".eo-cart-qty-form");
        const focus = focusedForm ? {id: productId(focusedForm), delta: active.dataset.eoDelta} : null;
        // PHP supplies the persisted mixed product/reward order, including changes from other tabs.
        root.replaceChildren(...updated.childNodes);
        enhance();
        lockOtherActions(true);
        if (focus) {
            const form = findForm(focus.id);
            const target = form && (focus.delta ? Array.from(form.querySelectorAll("[data-eo-delta]")).find(button => button.dataset.eoDelta === focus.delta && !button.disabled) : form.querySelector('[name="qty"]'));
            if (target) target.focus({preventScroll: true});
            else if (form) form.querySelector('[name="qty"]').focus({preventScroll: true});
        }
        window.scrollTo(x, y);
    }
    async function saveQueue() {
        if (busy || uncertain || queue.size === 0) return;
        busy = true;
        root.setAttribute("aria-busy", "true");
        lockOtherActions(true);
        let feedback = null;
        try {
            while (queue.size > 0) {
                const [id, quantity] = queue.entries().next().value;
                queue.delete(id);
                const form = findForm(id);
                if (!form) continue;
                const body = new FormData(form);
                body.set("qty", String(quantity));
                body.set("updatebtn", "1");
                const response = await fetch(form.action, {method: "POST", body, credentials: "same-origin", cache: "no-store"});
                if (new URL(response.url).pathname.endsWith("/login.php")) {
                    uncertain = true;
                    queue.clear();
                    feedback = {kind: "error", title: "Please sign in", message: "Sign in again to update your cart.", signIn: true};
                    break;
                }
                if (!response.ok || !response.redirected || !new URL(response.url).pathname.endsWith("/cart.php")) throw new Error("Unexpected cart response");
                const page = new DOMParser().parseFromString(await response.text(), "text/html");
                const updated = page.querySelector(".eo-cart-page");
                if (!updated) throw new Error("Missing cart state");
                feedback = readFeedback(page, updated);
                // Errors appear in the toast during async updates; native forms retain inline errors.
                updated.querySelectorAll(".eo-cart-message").forEach(node => node.remove());
                applyCart(updated);
            }
        } catch (error) {
            // The request may have committed. Never replay it or permit checkout using stale totals.
            uncertain = true;
            queue.clear();
            feedback = {kind: "error", title: "Check your cart", message: "We could not confirm the update. Refresh your cart before continuing."};
        } finally {
            busy = false;
            root.removeAttribute("aria-busy");
            if (uncertain) forms().forEach(form => {
                const input = form.querySelector('[name="qty"]');
                input.value = input.defaultValue;
                input.disabled = true;
                setBounds(form);
            });
            lockOtherActions(uncertain);
            if (feedback) notify(feedback.kind, feedback.title, feedback.message, findForm(lastProductId)?.querySelector('[name="qty"]'), feedback.signIn);
        }
    }
    function rowKey(row) {
        return row.dataset.cartProduct || "reward:" + row.querySelector('[name="redeem_id"]').value;
    }
    async function removeItem(form, button) {
        if (busy || uncertain) return;
        const row = form.closest(".eo-cart-product");
        const rows = Array.from(root.querySelectorAll(".eo-cart-product"));
        const index = rows.indexOf(row);
        const neighbour = rows[index + 1] || rows[index - 1];
        const neighbourKey = neighbour ? rowKey(neighbour) : null;
        const body = new FormData(form);
        body.set(button.name, button.value);
        busy = true;
        root.setAttribute("aria-busy", "true");
        lockOtherActions(true);
        forms().forEach(form => {
            form.querySelector('[name="qty"]').disabled = true;
            form.querySelectorAll("[data-eo-delta]").forEach(step => { step.disabled = true; });
        });
        let feedback = null;
        let focusTarget = null;
        let restoreFocus = false;
        try {
            const response = await fetch(form.action, {method: "POST", body, credentials: "same-origin", cache: "no-store"});
            if (new URL(response.url).pathname.endsWith("/login.php")) {
                uncertain = true;
                feedback = {kind: "error", title: "Please sign in", message: "Sign in again to update your cart.", signIn: true};
            } else {
                if (!response.ok || !response.redirected || !new URL(response.url).pathname.endsWith("/cart.php")) throw new Error("Unexpected cart response");
                const page = new DOMParser().parseFromString(await response.text(), "text/html");
                const updated = page.querySelector(".eo-cart-page");
                if (!updated) throw new Error("Missing cart state");
                feedback = readFeedback(page, updated);
                restoreFocus = document.activeElement === document.body || form.contains(document.activeElement);
                updated.querySelectorAll(".eo-cart-message").forEach(node => node.remove());
                applyCart(updated);
                const nextRow = Array.from(root.querySelectorAll(".eo-cart-product")).find(row => rowKey(row) === neighbourKey);
                focusTarget = nextRow?.querySelector(".eo-cart-remove") || root.querySelector(".eo-cart-secondary,.eo-cart-browse");
            }
        } catch (error) {
            // A lost response may follow a committed removal/refund. Never replay the request.
            uncertain = true;
            feedback = {kind: "error", title: "Check your cart", message: "We could not confirm the removal. Refresh your cart before continuing."};
        } finally {
            busy = false;
            root.removeAttribute("aria-busy");
            lockOtherActions(uncertain);
            forms().forEach(form => {
                form.querySelector('[name="qty"]').disabled = uncertain;
                setBounds(form);
            });
            if (restoreFocus && focusTarget && !uncertain) focusTarget.focus({preventScroll: true});
            if (feedback) notify(feedback.kind, feedback.title, feedback.message, focusTarget, feedback.signIn, feedback.action);
        }
    }


    function saveQuantity(form, number) {
        const input = form.querySelector('[name="qty"]');
        if (uncertain || !Number.isFinite(number) || !Number.isInteger(number)) {
            input.value = input.defaultValue;
            return;
        }
        const next = Math.max(Number(input.min), Math.min(Number(input.max), number));
        lastProductId = productId(form);
        input.value = String(next);
        setBounds(form);
        if (canUpdate) {
            if (next === Number(input.defaultValue) && !busy) return;
            queue.set(productId(form), next);
            saveQueue();
        } else if (!busy && next !== Number(input.defaultValue)) {
            form.requestSubmit(form.querySelector('[name="updatebtn"]'));
        }
    }
    root.addEventListener("click", function (event) {
        const checkout = event.target.closest("a[aria-disabled='true']");
        if (checkout) { event.preventDefault(); return; }
        const button = event.target.closest("[data-eo-delta]");
        if (!button || button.disabled) return;
        const form = button.closest(".eo-cart-qty-form"), input = form.querySelector('[name="qty"]');
        saveQuantity(form, Number(input.value) + Number(button.dataset.eoDelta));
    });
    root.addEventListener("change", function (event) {
        const input = event.target.closest('.eo-cart-qty-form input[name="qty"]');
        if (!input) return;
        if (input.value.trim() === "") input.value = input.defaultValue;
        else saveQuantity(input.form, Number(input.value));
    });
    root.addEventListener("submit", function (event) {
        if (event.defaultPrevented) return;
        const form = event.target.closest(".eo-cart-qty-form");
        if (busy || uncertain) { event.preventDefault(); return; }
        if (!form) {
            const button = event.submitter || event.target.querySelector('button[name="removebtn"],button[name="remove_rewardbtn"]');
            if (canUpdate && button && ["removebtn", "remove_rewardbtn"].includes(button.name)) {
                // Native confirmation runs first; Cancel sets defaultPrevented and sends nothing.
                event.preventDefault();
                removeItem(event.target, button);
            }
            return;
        }
        if (canUpdate) {
            event.preventDefault();
            saveQuantity(form, Number(form.querySelector('[name="qty"]').value));
        } else {
            busy = true;
            root.setAttribute("aria-busy", "true");
            lockOtherActions(true);
            root.querySelectorAll("[data-eo-delta]").forEach(button => { button.disabled = true; });
        }
    });
    window.addEventListener("pageshow", function (event) {
        if (event.persisted) window.location.reload();
    });
    enhance();
})();


// ===== SHARED CART NOTIFICATIONS: MENU, REWARDS AND CART =====
(function () {
    "use strict";
    const toast = document.querySelector("[data-cart-toast]");
    const announcer = document.querySelector("[data-cart-announcement]");
    if (!toast || !announcer) return;
    toast.querySelector("[data-cart-toast-close]").hidden = false;

    const title = toast.querySelector("[data-cart-toast-title]");
    const message = toast.querySelector("[data-cart-toast-message]");
    const action = toast.querySelector("[data-cart-toast-action]");
    let closeTimer;
    let hideTimer;
    let revealFrame;
    let announceTimer;
    let lastButton;

    function scheduleClose() {
        clearTimeout(closeTimer);
        if (toast.dataset.kind === "success" && !toast.hidden &&
            !toast.matches(":hover") && !toast.contains(document.activeElement)) {
            closeTimer = setTimeout(hideToast, 4500);
        }
    }

    function hideToast() {
        clearTimeout(closeTimer);
        clearTimeout(announceTimer);
        cancelAnimationFrame(revealFrame);
        toast.classList.remove("is-visible");
        hideTimer = setTimeout(function () { toast.hidden = true; }, 170);
    }

    function showToast(kind, text, signIn, heading, origin, showAction = true, actionHref = "cart.php", actionLabel = "View cart") {
        clearTimeout(closeTimer);
        clearTimeout(hideTimer);
        clearTimeout(announceTimer);
        cancelAnimationFrame(revealFrame);
        toast.dataset.kind = kind;
        title.textContent = heading || (kind === "success" ? "Added to cart" : "Could not add item");
        lastButton = origin || null;
        message.textContent = text;
        action.textContent = signIn ? "Sign in" : actionLabel;
        action.setAttribute("href", signIn ? "login.php" : actionHref);
        action.hidden = showAction === false && !signIn;
        toast.toggleAttribute("data-cart-no-action", action.hidden);
        toast.hidden = false;
        revealFrame = requestAnimationFrame(function () { toast.classList.add("is-visible"); });
        announcer.textContent = "";
        announceTimer = setTimeout(function () { announcer.textContent = title.textContent + ". " + text; }, 30);
        scheduleClose();
    }

    toast.querySelector("[data-cart-toast-close]").addEventListener("click", function () {
        if (toast.contains(document.activeElement) && lastButton && lastButton.isConnected) lastButton.focus({preventScroll: true});
        hideToast();
    });
    toast.addEventListener("mouseenter", function () { clearTimeout(closeTimer); });
    toast.addEventListener("mouseleave", scheduleClose);
    toast.addEventListener("focusin", function () { clearTimeout(closeTimer); });
    toast.addEventListener("focusout", function () { setTimeout(scheduleClose, 0); });
    toast.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            event.preventDefault();
            if (lastButton && lastButton.isConnected) lastButton.focus({preventScroll: true});
            hideToast();
        }
    });

    document.addEventListener("eo-cart-feedback", function (event) {
        const data = event.detail;
        showToast(data.kind, data.message, data.signIn, data.title, data.origin, data.showAction, data.actionHref, data.actionLabel);
    });
    if (toast.hasAttribute("data-cart-initial")) {
        // Server-rendered flash feedback is consumed once, including after a native POST.
        toast.removeAttribute("role");
        announcer.textContent = title.textContent + ". " + message.textContent;
        scheduleClose();
    }
    window.addEventListener("pageshow", function (event) {
        if (event.persisted) hideToast();
    });
})();

// ===== MENU: ADD TO CART WITHOUT LEAVING THE CURRENT VIEW =====
(function () {
    "use strict";
    const menu = document.querySelector("#main.eo-customer-menu");
    if (!menu || !document.querySelector("[data-cart-toast]") || !window.fetch || !window.FormData) return;
    const pending = new WeakSet();
    let lastButton;
    function showToast(kind, message, signIn) {
        document.dispatchEvent(new CustomEvent("eo-cart-feedback", {detail: {kind, message, signIn, origin: lastButton}}));
    }
    menu.addEventListener("submit", async function (event) {
        const form = event.target.closest("form[data-menu-add-to-cart]");
        if (!form || !menu.contains(form)) return;
        event.preventDefault();
        if (pending.has(form)) return;
        const button = form.querySelector("button[type='submit']");
        if (!button) return;
        pending.add(form);
        lastButton = button;
        button.setAttribute("aria-disabled", "true");
        button.setAttribute("aria-busy", "true");
        try {
            const response = await fetch(form.action, {
                method: "POST", body: new FormData(form), credentials: "same-origin",
                headers: {Accept: "application/json"}, cache: "no-store"
            });
            if (response.redirected && new URL(response.url).pathname.endsWith("/login.php")) {
                showToast("error", "Please sign in again to add items to your cart.", true);
                return;
            }
            if (!(response.headers.get("Content-Type") || "").includes("application/json")) throw new Error("Unexpected response");
            const result = await response.json();
            if (typeof result.ok !== "boolean" || typeof result.message !== "string") throw new Error("Invalid response");
            const added = response.ok && result.ok;
            showToast(added ? "success" : "error", result.message, response.status === 401 || result.code === "auth");
        } catch (error) {
            // Do not replay an uncertain request: it may already have committed.
            showToast("error", "We could not confirm the update. Check your cart before trying again.", false);
        } finally {
            pending.delete(form);
            button.removeAttribute("aria-disabled");
            button.removeAttribute("aria-busy");
        }
    });
})();

// ===== REWARDS: KEEP THE VIEW AND REFRESH AUTHORITATIVE BALANCE/CARDS =====
(function () {
    "use strict";
    const root = document.querySelector("#main.reward-page");
    if (!root || !document.querySelector("[data-cart-toast]") || !window.fetch || !window.FormData || !window.DOMParser) return;
    let busy = false;
    function notify(kind, title, message, origin, signIn = false) {
        document.dispatchEvent(new CustomEvent("eo-cart-feedback", {detail: {kind, title, message, origin, signIn}}));
    }
    window.addEventListener("pageshow", function (event) {
        // A restored page may hold an old single-use token or point balance.
        if (event.persisted) window.location.reload();
    });
    root.addEventListener("submit", async function (event) {
        const form = event.target.closest("form[data-reward-redeem]");
        // Keep the existing native confirmation: Cancel must never send a request.
        if (!form || !root.contains(form) || event.defaultPrevented) return;
        event.preventDefault();
        if (busy) return;
        const button = form.querySelector('[name="redeembtn"]');
        if (!button) return;
        const id = form.querySelector('[name="reward_id"]').value;
        const body = new FormData(form);
        body.set("redeembtn", button.value);
        const position = {x: window.scrollX, y: window.scrollY};
        busy = true;
        root.setAttribute("aria-busy", "true");
        button.setAttribute("aria-disabled", "true");
        try {
            const response = await fetch(form.action, {
                method: "POST", body, credentials: "same-origin", cache: "no-store"
            });
            if (new URL(response.url).pathname.endsWith("/login.php")) {
                notify("error", "Please sign in", "Sign in again to redeem rewards.", button, true);
                return;
            }
            if (!response.ok || !response.redirected || !new URL(response.url).pathname.endsWith("/reward.php")) throw new Error("Unexpected response");
            const page = new DOMParser().parseFromString(await response.text(), "text/html");
            const updated = page.querySelector("#main.reward-page");
            if (!updated || !updated.querySelector("#points-banner")) throw new Error("Invalid reward response");
            const feedback = page.querySelector("[data-cart-initial]");
            const error = updated.querySelector(".reward-message");
            if (!feedback && !error) throw new Error("Missing reward feedback");
            const hadFocus = form.contains(document.activeElement);
            root.replaceChildren(...updated.childNodes);
            const card = Array.from(root.querySelectorAll("[data-reward-id]")).find(item => item.dataset.rewardId === id);
            const nextButton = card && card.querySelector('[name="redeembtn"]');
            const focusTarget = nextButton || root.querySelector("h1");
            if (hadFocus && focusTarget) focusTarget.focus({preventScroll: true});
            window.scrollTo(position.x, position.y);
            if (feedback) {
                notify("success", feedback.querySelector("[data-cart-toast-title]").textContent,
                    feedback.querySelector("[data-cart-toast-message]").textContent, focusTarget);
            } else notify("error", "Could not redeem reward", error.textContent, focusTarget);
        } catch (error) {
            // Never replay an uncertain redemption: it may already have committed.
            notify("error", "Check your cart", "We could not confirm the redemption. Check your cart before trying again.", button);
        } finally {
            busy = false;
            root.removeAttribute("aria-busy");
            button.removeAttribute("aria-disabled");
        }
    });
})();

// ===== CUSTOMER NAVIGATION AND AUTH TRANSITIONS =====
(function()
{
	"use strict";
	const navbar = document.getElementById("navbar");
	if(!navbar) return;
	let latestRequest = 0;

	async function refreshNavigation()
	{
		if(document.visibilityState==="hidden") return;
		const request = ++latestRequest;
		try
		{
			const currentPage = window.location.pathname.split("/").pop() || "index.php";
			const response = await fetch("customer_navigation.php?page="+encodeURIComponent(currentPage),{
				credentials:"same-origin",
				cache:"no-store"
			});
			if(!response.ok) return;
			const markup = await response.text();
			if(request!==latestRequest) return;
			const updated = document.createElement("div");
			updated.innerHTML = markup;
			const customerLoggedIn = Boolean(updated.querySelector('a[href="logout.php"]'));
			const footerMenu = document.querySelector(".home-footer [data-customer-menu-link]");
			if(footerMenu)
			{
				footerMenu.setAttribute("href",customerLoggedIn ? "category.php" : "guest_menu.php");
			}
			const previouslyLoggedIn = Boolean(navbar.querySelector('a[href="logout.php"]'));
			if(customerLoggedIn!==previouslyLoggedIn && document.body.hasAttribute("data-session-aware"))
			{
				window.location.reload();
				return;
			}
			const orderNow = document.querySelector(".home-cta");
			if(orderNow)
			{
				orderNow.setAttribute("href",customerLoggedIn
					? "category.php"
					: "login.php");
			}
			if(navbar.innerHTML===updated.innerHTML) return;
			const focusedLink = navbar.contains(document.activeElement)
				? document.activeElement.getAttribute("href")
				: null;
			navbar.replaceChildren(...updated.childNodes);
			if(focusedLink)
			{
				const match = Array.from(navbar.querySelectorAll("a"))
					.find(link=>link.getAttribute("href")===focusedLink);
				if(match) match.focus();
			}
		}
		catch(error)
		{
			// Retain the server-rendered links when a connection is unavailable.
		}
	}

	refreshNavigation();
	window.addEventListener("pageshow",function(event)
	{
		if(event.persisted) refreshNavigation();
	});
	window.addEventListener("focus",refreshNavigation);
	document.addEventListener("visibilitychange",refreshNavigation);
})();

// Enhance auth links and registration feedback, retaining the native PHP fallback.
(function () {
    "use strict";
    const root = document.querySelector("#main.eo-auth-page");
    if (!root) return;
    const frame = root.querySelector(".eo-auth-frame");
    const showcase = frame.querySelector(".eo-auth-showcase");
    const heading = showcase.querySelector(".eo-auth-showcase-heading");
    let busy = false;
    let queued = null;
    let submitting = false;

    function authView(url) {
        if (url.origin !== location.origin || url.search || url.hash) return null;
        const directory = location.pathname.slice(0, location.pathname.lastIndexOf("/") + 1);
        if (url.pathname === directory + "login.php") return "login";
        if (url.pathname === directory + "register.php") return "signup";
        return null;
    }

    function executeInitializer(source) {
        const script = document.createElement("script");
        script.textContent = source;
        document.body.appendChild(script);
        script.remove();
    }

    function animate(element, frames, options, reduced) {
        if (reduced || !element.animate) return null;
        return element.animate(frames, Object.assign({fill: "both"}, options));
    }

    async function prepareAuthPage(incoming, url) {
        const view = authView(url);
        const nextRoot = incoming.querySelector("#main.eo-auth-page");
        const nextPanel = nextRoot && nextRoot.querySelector(".eo-auth-form-panel");
        const initializer = incoming.querySelector('script[data-eo-auth-init="' + view + '"]');
        const stylesheet = incoming.querySelector('style[data-eo-auth-style="' + view + '"]');
        if (!nextRoot || !nextPanel || !initializer || !stylesheet) throw new Error("Auth page unavailable");
        if (!document.querySelector('style[data-eo-auth-style="' + view + '"]')) {
            document.head.appendChild(document.importNode(stylesheet, true));
        }
        const feedbackScript = incoming.querySelector('script[src^="public.js"]');
        let feedbackSource = "";
        if (feedbackScript) {
            const feedbackResponse = await fetch(new URL(feedbackScript.getAttribute("src"), url), {credentials: "same-origin"});
            if (!feedbackResponse.ok) throw new Error("Password feedback unavailable");
            feedbackSource = await feedbackResponse.text();
        }
        return {nextRoot: nextRoot, nextPanel: nextPanel, initializer: initializer, feedbackSource: feedbackSource};
    }

    function initializePanel(data) {
        if (data.nextRoot.dataset.startStep) root.dataset.startStep = data.nextRoot.dataset.startStep;
        else delete root.dataset.startStep;
        executeInitializer(data.initializer.textContent);
        if (data.feedbackSource) executeInitializer(data.feedbackSource);
    }

    async function switchPage(url, historyMode, incoming) {
        const view = authView(url);
        if (!view) { location.assign(url.href); return; }
        if (busy || submitting) { queued = {url: url, historyMode: historyMode}; return; }
        if (root.dataset.authView === view) return;
        busy = true;
        root.dataset.authTransition = "loading";
        root.setAttribute("aria-busy", "true");
        try {
            if (!incoming) {
                const response = await fetch(url.href, {credentials: "same-origin", cache: "no-store"});
                if (!response.ok || response.url !== url.href) { location.assign(response.url || url.href); return; }
                incoming = new DOMParser().parseFromString(await response.text(), "text/html");
            }
            const data = await prepareAuthPage(incoming, url);
            const nextRoot = data.nextRoot;
            const nextPanel = data.nextPanel;
            const image = showcase.querySelector("img");
            if (image.decode) await image.decode();
            const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
            const oldPanel = frame.querySelector(".eo-auth-form-panel");
            const width = root.getBoundingClientRect().width;
            root.style.width = width + "px";
            root.inert = true;
            root.dataset.authTransition = "running";
            // Keep the outgoing form in its original column while the opaque photo
            // wipes across it. Both forms remain beneath that same photo surface.
            oldPanel.style.left = getComputedStyle(oldPanel).left;
            oldPanel.inert = true;
            oldPanel.setAttribute("aria-hidden", "true");
            const start = getComputedStyle(showcase).transform;
            showcase.style.transform = start;
            const panel = document.importNode(nextPanel, true);
            panel.style.opacity = "0";
            frame.appendChild(panel);
            nextRoot.classList.forEach(function (name) { root.classList.add(name); });
            root.dataset.authView = view;
            frame.className = nextRoot.querySelector(".eo-auth-frame").className;
            initializePanel(data);
            document.title = incoming.title;
            if (historyMode === "push") history.pushState(null, "", url.href);
            document.querySelectorAll("#navbar a").forEach(function (link) {
                if (new URL(link.href).pathname === url.pathname) link.setAttribute("aria-current", "page");
                else link.removeAttribute("aria-current");
            });
            const end = "translateX(" + (view === "signup" ? frame.clientWidth * .57 : 0) + "px)";
            const motion = animate(showcase, [{transform: start}, {transform: end}], {duration: 650, easing: "cubic-bezier(.65,0,.25,1)"}, reduced);
            const outgoing = animate(oldPanel, [{opacity: 1}, {opacity: 0}], {duration: 280, easing: "ease-out"}, reduced);
            const formFade = animate(panel, [{opacity: 0}, {opacity: 1}], {duration: 300, delay: 250, easing: "ease-out"}, reduced);
            const oldHeading = animate(heading, [{opacity: 1}, {opacity: 0}], {duration: 140}, reduced);
            if (oldHeading) await oldHeading.finished;
            heading.innerHTML = nextRoot.querySelector(".eo-auth-showcase-heading").innerHTML;
            heading.style.opacity = "0";
            if (oldHeading) oldHeading.cancel();
            const headingFade = animate(heading, [{opacity: 0}, {opacity: 1}], {duration: 260, delay: 60, easing: "ease-out"}, reduced);
            await Promise.all([motion, outgoing, formFade, headingFade].filter(Boolean).map(function (animation) { return animation.finished; }));
            oldPanel.remove();
            root.className = nextRoot.className;
            showcase.style.transform = "";
            panel.style.opacity = "";
            heading.style.opacity = "";
            [motion, outgoing, formFade, headingFade].filter(Boolean).forEach(function (animation) { animation.cancel(); });
            const title = panel.querySelector("h1,h2");
            root.inert = false;
            if (title) {
                title.tabIndex = -1;
                title.setAttribute("data-eo-auth-focus", "");
                title.focus({preventScroll: true});
            }
        } catch (error) {
            // A failed enhancement uses the original server-rendered route.
            location.assign(url.href);
        } finally {
            root.style.width = "";
            root.inert = false;
            root.removeAttribute("aria-busy");
            root.dataset.authTransition = "idle";
            busy = false;
            const next = queued;
            queued = null;
            if (next) switchPage(next.url, next.historyMode);
        }
    }

    document.addEventListener("submit", async function (event) {
        const form = event.target;
        if (event.defaultPrevented || !form.matches("#signup-form") || !root.contains(form)
            || !window.fetch || !window.FormData) return;
        const url = new URL(form.action);
        if (authView(url) !== "signup" || form.method.toLowerCase() !== "post") return;
        event.preventDefault();
        if (busy || submitting) return;

        const payload = new FormData(form);
        const position = {left: scrollX, top: scrollY};
        submitting = true;
        root.inert = true;
        root.setAttribute("aria-busy", "true");
        root.dataset.authTransition = "submitting";
        const previousAnchor = root.style.overflowAnchor;
        root.style.overflowAnchor = "none";
        try {
            const response = await fetch(url.href, {
                method: "POST", body: payload, credentials: "same-origin", cache: "no-store"
            });
            if (!response.ok) throw new Error("Registration response unavailable");
            const destination = new URL(response.url);
            const incoming = new DOMParser().parseFromString(await response.text(), "text/html");
            if (authView(destination) === "login") {
                // Reuse this exact response so the PHP success notice is not consumed twice.
                submitting = false;
                await switchPage(destination, "push", incoming);
            } else if (authView(destination) === "signup") {
                const data = await prepareAuthPage(incoming, destination);
                const panel = document.importNode(data.nextPanel, true);
                // Retain typed passwords only in the live browser form, never in HTML or storage.
                ["cust_password", "cust_confirm_password"].forEach(function (name) {
                    const input = panel.querySelector('[name="' + name + '"]');
                    const value = payload.get(name);
                    if (input && typeof value === "string") input.value = value;
                });
                frame.querySelector(".eo-auth-form-panel").replaceWith(panel);
                root.inert = false;
                initializePanel(data);
                document.title = incoming.title;
            } else {
                // Follow a completed server redirect with a GET; never replay the POST.
                location.assign(destination.href);
            }
        } catch (error) {
            const currentForm = root.querySelector("#signup-form");
            if (currentForm) {
                let notice = currentForm.parentElement.querySelector("[data-eo-registration-error]");
                if (!notice) {
                    notice = document.createElement("p");
                    notice.className = "form-message form-message-error";
                    notice.setAttribute("role", "alert");
                    notice.setAttribute("data-eo-registration-error", "");
                    currentForm.before(notice);
                }
                notice.textContent = "We couldn't confirm your registration. Please try signing in before submitting again.";
            }
        } finally {
            submitting = false;
            root.style.overflowAnchor = previousAnchor;
            if (!busy) {
                root.inert = false;
                root.removeAttribute("aria-busy");
                root.dataset.authTransition = "idle";
            }
            if (scrollX !== position.left || scrollY !== position.top) scrollTo(Object.assign({behavior: "instant"}, position));
            const next = queued;
            queued = null;
            if (next) switchPage(next.url, next.historyMode);
        }
    });

    document.addEventListener("click", function (event) {
        const link = event.target.closest("a[href]");
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute("download") || (link.target && link.target !== "_self")) return;
        const url = new URL(link.href);
        if (!authView(url)) return;
        event.preventDefault();
        switchPage(url, "push");
    });
    window.addEventListener("popstate", function () {
        const url = new URL(location.href);
        if (authView(url)) switchPage(url, "none");
    });
})();

;

// ===== HOME CATEGORY CAROUSEL =====
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

;

// ===== ABOUT TEAM CAROUSEL =====
(function () {
    "use strict";

    function init(root) {
        const cards = Array.from(root.querySelectorAll("[data-orbit-card]"));
        const stage = root.querySelector(".eo-orbit-stage");
        const prev = root.querySelector("[data-orbit-prev]");
        const next = root.querySelector("[data-orbit-next]");
        const status = root.querySelector("[data-orbit-status]");
        if (cards.length !== 3 || !stage || !prev || !next || root.classList.contains("is-ready")) return;

        const initial = cards.findIndex(card => card.hasAttribute("data-orbit-initial"));
        let centerIndex = initial < 0 ? 1 : initial;
        let targetIndex = centerIndex;
        let moving = false;
        let started = 0;
        let frame = 0;
        const duration = 1180;
        const sideOpacity = 0.68;
        const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
        const clamp = value => Math.max(0, Math.min(1, value));
        const ease = t => t < 0.5 ? 4 * t * t * t : 1 - Math.pow(2 - 2 * t, 3) / 2;
        const lerp = (a, b, t) => a + (b - a) * t;

        function slot(index, center) {
            const delta = (index - center + cards.length) % cards.length;
            return delta === 0 ? 0 : delta === 1 ? 1 : -1;
        }

        function pose(position) {
            const compact = stage.clientWidth < 640;
            if (position === 0) return {x: 0, y: 0, z: compact ? 40 : 80, scale: 1, opacity: 1};
            const radius = compact ? stage.clientWidth * 0.66 : Math.min(364, stage.clientWidth * 0.255 + 24);
            return {x: position * radius, y: compact ? 30 : 38, z: 0, scale: compact ? 0.78 : 0.98, opacity: sideOpacity};
        }

        function interpolate(a, b, t) {
            return {
                x: lerp(a.x, b.x, t), y: lerp(a.y, b.y, t), z: lerp(a.z, b.z, t),
                scale: lerp(a.scale, b.scale, t), opacity: lerp(a.opacity, b.opacity, t)
            };
        }

        function draw(card, value) {
            card.style.transform = `translate3d(${value.x.toFixed(2)}px,${value.y.toFixed(2)}px,${value.z.toFixed(2)}px) scale(${value.scale.toFixed(4)})`;
            card.style.opacity = value.opacity.toFixed(4);
            card.style.zIndex = String(Math.round(value.z) + 120);
            card.style.setProperty("--eo-card-emphasis", clamp((value.opacity - sideOpacity) / (1 - sideOpacity)).toFixed(4));
        }

        function busy(value) {
            root.dataset.moving = String(value);
            root.classList.toggle("is-moving", value);
            stage.setAttribute("aria-busy", String(value));
            prev.setAttribute("aria-disabled", String(value));
            next.setAttribute("aria-disabled", String(value));
        }

        function still(announce) {
            cards.forEach(function (card, index) {
                const position = slot(index, centerIndex);
                draw(card, pose(position));
                card.dataset.position = position === 0 ? "center" : position < 0 ? "left" : "right";
                card.setAttribute("aria-hidden", String(position !== 0));
            });
            root.dataset.activeIndex = String(centerIndex);
            if (announce && status) {
                const card = cards[centerIndex];
                const name = card.querySelector(".eo-orbit-name").textContent.trim();
                const role = card.querySelector(".eo-orbit-role").textContent.trim();
                status.textContent = `${name}, ${role}. Member ${centerIndex + 1} of ${cards.length}.`;
            }
        }

        function finish(announce) {
            if (frame) cancelAnimationFrame(frame);
            frame = 0;
            if (moving) centerIndex = targetIndex;
            moving = false;
            busy(false);
            still(announce);
        }

        function animate(now) {
            if (!moving) return;
            const raw = clamp((now - started) / duration);
            const t = ease(raw);
            cards.forEach(function (card, index) {
                const before = slot(index, centerIndex);
                const after = slot(index, targetIndex);
                let value;
                if (before !== 0 && after !== 0 && before !== after) {
                    // The remaining side card passes behind the other two.
                    const back = {x: 0, y: -24, z: -100, scale: 0.66, opacity: 0};
                    value = t < 0.5
                        ? interpolate(pose(before), back, ease(t * 2))
                        : interpolate(back, pose(after), ease((t - 0.5) * 2));
                } else {
                    value = interpolate(pose(before), pose(after), t);
                    value.y -= 12 * Math.sin(Math.PI * t);
                    if (after === 0) value.z += 18 * Math.sin(Math.PI * t);
                }
                draw(card, value);
            });
            if (raw < 1) frame = requestAnimationFrame(animate);
            else finish(true);
        }

        function change(direction) {
            if (moving) return;
            targetIndex = (centerIndex + direction + cards.length) % cards.length;
            if (reducedMotion.matches) {
                centerIndex = targetIndex;
                still(true);
                return;
            }
            moving = true;
            busy(true);
            started = performance.now();
            frame = requestAnimationFrame(animate);
        }

        cards.forEach(function (card, index) {
            card.setAttribute("role", "group");
            card.setAttribute("aria-roledescription", "slide");
            card.setAttribute("aria-label", `${index + 1} of ${cards.length}`);
        });
        prev.addEventListener("click", () => change(-1));
        next.addEventListener("click", () => change(1));
        root.classList.add("is-ready");
        busy(false);
        still(false);

        const resize = () => finish(moving);
        if ("ResizeObserver" in window) new ResizeObserver(resize).observe(stage);
        else window.addEventListener("resize", resize);
        reducedMotion.addEventListener("change", function () {
            if (reducedMotion.matches && moving) finish(true);
        });
    }

    function ready() {
        document.querySelectorAll("[data-eo-orbit]").forEach(init);
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", ready);
    else ready();
})();

;

// ===== CONTACT MESSAGE FORM =====
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

;

// ===== CHECKOUT DELIVERY AND PAYMENT INTERACTIONS =====
/* EasyOrder Checkout — UI behaviour ONLY; checkout.php still validates/creates orders. */
(function () {
    'use strict';
    const page = document.querySelector('.eo-checkout-page');
    const form = document.getElementById('eo-checkout-form');
    if (!page || !form) return;

    const deliveryArea = document.getElementById('eo-checkout-delivery-address');
    const walletArea = document.getElementById('eo-checkout-wallet-area');
    const walletPin = document.getElementById('eo-wallet-pin');
    const feeLabel = document.getElementById('eo-checkout-fee');
    const totalLabel = document.getElementById('eo-checkout-total');
    const buttonLabel = document.getElementById('eo-checkout-button-label');
    const submitButton = document.getElementById('eo-checkout-submit');
    const feedback = document.getElementById('eo-checkout-feedback');
    const addressFields = [
        document.getElementById('eo-delivery-address'),
        document.getElementById('eo-delivery-city'),
        document.getElementById('eo-delivery-state'),
        document.getElementById('eo-delivery-postcode')
    ];
    let isSubmitting = false;
    const money = amount => 'RM ' + amount.toFixed(2);
    const selected = name => {
        const input = form.querySelector('input[name="' + name + '"]:checked');
        return input ? input.value : '';
    };
    const showError = (message, element) => {
        feedback.textContent = message;
        feedback.hidden = false;
        if (element) element.focus();
    };
    const clearError = () => {
        feedback.textContent = '';
        feedback.hidden = true;
    };

    function refresh() {
        const delivery = selected('delivery_method') === 'Delivery';
        const payment = selected('payment');
        const fee = delivery ? Number(page.dataset.deliveryFee) : 0;
        const total = Number(page.dataset.subtotal) + Number(page.dataset.sst) + fee;
        feeLabel.textContent = money(fee);
        totalLabel.textContent = money(total);
        deliveryArea.hidden = !delivery;
        addressFields.forEach(field => { field.required = delivery; });
        walletArea.hidden = payment !== 'EasyOrder Wallet';
        walletPin.required = payment === 'EasyOrder Wallet';
        if (payment !== 'EasyOrder Wallet') walletPin.value = '';
        buttonLabel.textContent = payment === 'Credit Card' ? 'Continue to Payment'
            : payment === 'EasyOrder Wallet' ? 'Pay with Wallet' : 'Place Order';
        clearError();
    }

    form.querySelectorAll('input[name="delivery_method"], input[name="payment"]').forEach(input => {
        input.addEventListener('change', refresh);
    });
    addressFields.concat(walletPin).forEach(field => {
        field.addEventListener('input', clearError);
        field.addEventListener('change', clearError);
    });

    form.addEventListener('submit', event => {
        if (isSubmitting) { event.preventDefault(); return; }
        clearError();
        const payment = selected('payment');
        if (!payment) {
            event.preventDefault();
            showError('Please select a payment method.', form.querySelector('input[name="payment"]'));
            return;
        }
        if (selected('delivery_method') === 'Delivery') {
            for (const field of addressFields) {
                if (!field.value.trim()) {
                    event.preventDefault();
                    showError('Please complete all delivery address fields.', field);
                    return;
                }
            }
            const postcode = document.getElementById('eo-delivery-postcode');
            if (!/^\d{5}$/.test(postcode.value.trim())) {
                event.preventDefault();
                showError('Please enter a valid 5-digit postcode.', postcode);
                return;
            }
        }
        if (payment === 'EasyOrder Wallet' && !/^\d{6}$/.test(walletPin.value)) {
            event.preventDefault();
            showError('Please enter your 6-digit Wallet PIN.', walletPin);
            return;
        }
        // Preserve the native submitter name/value (placeorderbtn=1), expected by checkout.php.
        isSubmitting = true;
        // Do not disable the submit button here: browsers can omit its name/value
        // from the POST, and checkout.php relies on placeorderbtn=1.
        submitButton.setAttribute('aria-disabled', 'true');
        buttonLabel.textContent = 'Processing...';
    });

    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });
    window.addEventListener('pagehide', () => { walletPin.value = ''; });
    refresh();
})();

// ===== CUSTOMER ORDER HISTORY DATE FILTER =====
(function () {
    "use strict";
    const root = document.querySelector("#main.eo-order-history");
    if (!root) return;
    const dateFilter = root.querySelector("#eo-history-date-filter");
    const filters = root.querySelector(".eo-history-filters");
    const panel = root.querySelector("#eo-history-custom-range");
    const trigger = root.querySelector("#eo-history-range-trigger");
    const close = root.querySelector("#eo-history-calendar-close");
    const fromLabel = root.querySelector("#eo-history-range-from");
    const toLabel = root.querySelector("#eo-history-range-to");
    const feedback = root.querySelector("#eo-history-range-feedback");
    const monthSelect = root.querySelector("#eo-history-calendar-month");
    const yearSelect = root.querySelector("#eo-history-calendar-year");
    const heading = root.querySelector("#eo-history-calendar-heading");
    const days = root.querySelector("#eo-history-calendar-days");
    const clear = root.querySelector("#eo-history-clear-dates");
    const noResults = root.querySelector("#eo-history-no-results");
    const rows = Array.from(root.querySelectorAll(".eo-history-row"));
    if (![dateFilter, filters, panel, trigger, close, fromLabel, toLabel, feedback, monthSelect, yearSelect, heading, days, clear, noResults].every(Boolean)) return;

    const DAY = 86400000;
    const shortDate = new Intl.DateTimeFormat("en-GB", {day: "numeric", month: "short", year: "numeric", timeZone: "UTC"});
    const fullDate = new Intl.DateTimeFormat("en-GB", {weekday: "long", day: "numeric", month: "long", year: "numeric", timeZone: "UTC"});
    const monthName = new Intl.DateTimeFormat("en-GB", {month: "long", timeZone: "UTC"});
    function dayIndex(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        const parts = value.split("-").map(Number);
        const stamp = Date.UTC(parts[0], parts[1] - 1, parts[2]);
        return Number.isFinite(stamp) && new Date(stamp).toISOString().slice(0, 10) === value ? stamp / DAY : null;
    }
    function malaysiaToday() {
        const parts = new Intl.DateTimeFormat("en-CA", {timeZone: "Asia/Kuala_Lumpur", year: "numeric", month: "2-digit", day: "2-digit"}).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
        return dayIndex(values.year + "-" + values.month + "-" + values.day);
    }
    const dateOf = day => new Date(day * DAY);
    const monthOf = day => dateOf(day).getUTCFullYear() * 12 + dateOf(day).getUTCMonth();
    const today = malaysiaToday();
    const firstYear = 1900;
    const lastYear = dateOf(today).getUTCFullYear() + 1;
    const minimumDay = Date.UTC(firstYear, 0, 1) / DAY;
    const maximumDay = Date.UTC(lastYear, 11, 31) / DAY;
    const clampDay = day => Math.min(maximumDay, Math.max(minimumDay, day));
    let fromDay = null, toDay = null, previewDay = null;
    let focusedDay = today, viewMonth = monthOf(today);
    let calendarOpen = false;

    for (let month = 0; month < 12; month++) {
        monthSelect.add(new Option(monthName.format(new Date(Date.UTC(2000, month, 1))), String(month)));
    }
    for (let year = firstYear; year <= lastYear; year++) yearSelect.add(new Option(String(year), String(year)));

    function paintSelection() {
        const complete = fromDay !== null && toDay !== null;
        const preview = fromDay !== null && toDay === null && previewDay !== null;
        const low = preview ? Math.min(fromDay, previewDay) : fromDay;
        const high = preview ? Math.max(fromDay, previewDay) : toDay;
        days.querySelectorAll("button[data-day]").forEach(button => {
            const day = Number(button.dataset.day), cell = button.parentElement;
            const start = day === fromDay, end = day === toDay;
            const inside = (complete || preview) && day >= low && day <= high;
            cell.classList.toggle("is-in-range", complete && inside);
            cell.classList.toggle("is-preview", preview && inside);
            cell.classList.toggle("is-range-start", start);
            cell.classList.toggle("is-range-end", end);
            cell.setAttribute("aria-selected", String(start || end || (complete && inside)));
            button.setAttribute("aria-label", fullDate.format(dateOf(day)) + (start ? ", Start date" : "") + (end ? ", End date" : ""));
        });
    }

    function renderCalendar(moveFocus = false) {
        const year = Math.floor(viewMonth / 12), month = viewMonth % 12;
        const firstDay = Date.UTC(year, month, 1) / DAY;
        const offset = (dateOf(firstDay).getUTCDay() + 6) % 7;
        if (monthOf(focusedDay) !== viewMonth) focusedDay = firstDay;
        monthSelect.value = String(month);
        yearSelect.value = String(year);
        heading.textContent = monthName.format(dateOf(firstDay)) + " " + year;
        panel.querySelector('[data-history-month="-1"]').disabled = viewMonth <= firstYear * 12;
        panel.querySelector('[data-history-month="1"]').disabled = viewMonth >= lastYear * 12 + 11;
        const weeks = [];
        for (let week = 0; week < 6; week++) {
            const row = document.createElement("tr");
            for (let weekday = 0; weekday < 7; weekday++) {
                const day = firstDay - offset + week * 7 + weekday;
                const cell = document.createElement("td");
                cell.setAttribute("role", "gridcell");
                if (day >= minimumDay && day <= maximumDay) {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.dataset.day = String(day);
                    button.textContent = String(dateOf(day).getUTCDate());
                    button.tabIndex = day === focusedDay ? 0 : -1;
                    if (day === malaysiaToday()) button.setAttribute("aria-current", "date");
                    cell.classList.toggle("is-outside", monthOf(day) !== viewMonth);
                    cell.append(button);
                }
                row.append(cell);
            }
            weeks.push(row);
        }
        days.replaceChildren(...weeks);
        paintSelection();
        if (moveFocus) days.querySelector('button[data-day="' + focusedDay + '"]')?.focus({preventScroll: true});
    }

    function applyHistoryDateFilter() {
        const custom = dateFilter.value === "custom";
        const currentDay = malaysiaToday();
        const complete = fromDay !== null && toDay !== null;
        panel.hidden = !custom || !calendarOpen;
        trigger.hidden = !custom;
        trigger.setAttribute("aria-expanded", String(custom && calendarOpen));
        trigger.textContent = complete ? shortDate.format(dateOf(fromDay)) + " – " + shortDate.format(dateOf(toDay)) : "Select dates";
        fromLabel.textContent = fromDay === null ? "Select start date" : shortDate.format(dateOf(fromDay));
        toLabel.textContent = toDay === null ? "Select end date" : shortDate.format(dateOf(toDay));
        feedback.textContent = fromDay === null ? "Click a start date, then an end date."
            : toDay === null ? "Now click an end date."
            : "Dates selected. Click a date to start a new range.";
        clear.disabled = fromDay === null && toDay === null;
        let count = 0;
        rows.forEach(row => {
            const day = dayIndex(row.dataset.orderDate || "");
            const elapsed = day === null ? null : currentDay - day;
            const matches = custom ? (!complete || (day !== null && day >= fromDay && day <= toDay))
                : dateFilter.value === "all" || (elapsed !== null && elapsed >= 0 && elapsed < Number(dateFilter.value));
            row.hidden = !matches;
            if (matches) count++;
        });
        noResults.hidden = count !== 0;
    }

    function closeCalendar(moveFocus = false) {
        calendarOpen = false;
        previewDay = null;
        applyHistoryDateFilter();
        if (moveFocus) trigger.focus({preventScroll: true});
    }

    days.addEventListener("click", event => {
        const button = event.target.closest("button[data-day]");
        if (!button) return;
        const day = Number(button.dataset.day);
        if (fromDay === null || toDay !== null) {
            fromDay = day;
            toDay = null;
        } else {
            toDay = Math.max(fromDay, day);
            fromDay = Math.min(fromDay, day);
        }
        focusedDay = day;
        viewMonth = monthOf(day);
        previewDay = null;
        applyHistoryDateFilter();
        if (toDay !== null) closeCalendar(true);
        else renderCalendar(true);
    });
    function preview(event) {
        const button = event.target.closest("button[data-day]");
        if (button && fromDay !== null && toDay === null) {
            previewDay = Number(button.dataset.day);
            paintSelection();
        }
    }
    days.addEventListener("mouseover", preview);
    days.addEventListener("focusin", preview);
    days.addEventListener("mouseleave", () => { previewDay = null; paintSelection(); });

    panel.querySelectorAll("[data-history-month]").forEach(button => button.addEventListener("click", () => {
        viewMonth += Number(button.dataset.historyMonth);
        previewDay = null;
        renderCalendar();
    }));
    function chooseMonth() {
        viewMonth = Number(yearSelect.value) * 12 + Number(monthSelect.value);
        previewDay = null;
        renderCalendar();
    }
    monthSelect.addEventListener("change", chooseMonth);
    yearSelect.addEventListener("change", chooseMonth);

    days.addEventListener("keydown", event => {
        const button = event.target.closest("button[data-day]");
        if (!button) return;
        const day = Number(button.dataset.day), date = dateOf(day);
        let next = day;
        const offsets = {ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7};
        if (event.key in offsets) next += offsets[event.key];
        else if (event.key === "Home") next -= (date.getUTCDay() + 6) % 7;
        else if (event.key === "End") next += 6 - (date.getUTCDay() + 6) % 7;
        else if (event.key === "PageUp" || event.key === "PageDown") {
            const delta = (event.key === "PageUp" ? -1 : 1) * (event.shiftKey ? 12 : 1);
            const first = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + delta, 1));
            const last = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate();
            next = Date.UTC(first.getUTCFullYear(), first.getUTCMonth(), Math.min(date.getUTCDate(), last)) / DAY;
        } else return;
        event.preventDefault();
        focusedDay = clampDay(next);
        viewMonth = monthOf(focusedDay);
        renderCalendar(true);
    });

    clear.addEventListener("click", () => {
        fromDay = toDay = previewDay = null;
        focusedDay = malaysiaToday();
        viewMonth = monthOf(focusedDay);
        applyHistoryDateFilter();
        renderCalendar(true);
    });
    dateFilter.addEventListener("change", () => {
        calendarOpen = dateFilter.value === "custom";
        applyHistoryDateFilter();
        if (dateFilter.value === "custom") renderCalendar(true);
    });
    trigger.addEventListener("click", () => {
        if (calendarOpen) closeCalendar();
        else {
            calendarOpen = true;
            applyHistoryDateFilter();
            renderCalendar(true);
        }
    });
    close.addEventListener("click", () => closeCalendar(true));
    panel.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            event.preventDefault();
            closeCalendar(true);
        }
    });
    document.addEventListener("pointerdown", event => {
        if (calendarOpen && !filters.contains(event.target)) closeCalendar();
    });
    window.addEventListener("pageshow", () => { applyHistoryDateFilter(); renderCalendar(); });
    filters.hidden = false;
    applyHistoryDateFilter();
    renderCalendar();
})();
