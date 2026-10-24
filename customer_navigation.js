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

// Enhance only Login / Sign Up links; form POSTs remain native PHP requests.
(function () {
    "use strict";
    const root = document.querySelector("#main.eo-auth-page");
    if (!root) return;
    const frame = root.querySelector(".eo-auth-frame");
    const showcase = frame.querySelector(".eo-auth-showcase");
    const heading = showcase.querySelector(".eo-auth-showcase-heading");
    let busy = false;
    let queued = null;

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

    async function switchPage(url, historyMode) {
        const view = authView(url);
        if (!view) { location.assign(url.href); return; }
        if (busy) { queued = {url: url, historyMode: historyMode}; return; }
        if (root.dataset.authView === view) return;
        busy = true;
        root.dataset.authTransition = "loading";
        root.setAttribute("aria-busy", "true");
        try {
            const response = await fetch(url.href, {credentials: "same-origin", cache: "no-store"});
            if (!response.ok || response.url !== url.href) { location.assign(response.url || url.href); return; }
            const incoming = new DOMParser().parseFromString(await response.text(), "text/html");
            const nextRoot = incoming.querySelector("#main.eo-auth-page");
            const nextPanel = nextRoot && nextRoot.querySelector(".eo-auth-form-panel");
            const initializer = incoming.querySelector('script[data-eo-auth-init="' + view + '"]');
            const stylesheet = incoming.querySelector('style[data-eo-auth-style="' + view + '"]');
            if (!nextRoot || !nextPanel || !initializer || !stylesheet) throw new Error("Auth page unavailable");
            if (!document.querySelector('style[data-eo-auth-style="' + view + '"]')) {
                document.head.appendChild(document.importNode(stylesheet, true));
            }
            // Fetch feedback code before hiding the outgoing form; no new script listeners
            // are attached until the new controls are in the document.
            const feedbackScript = incoming.querySelector('script[src^="password_ux.js"]');
            let feedbackSource = "";
            if (feedbackScript) {
                const feedbackResponse = await fetch(new URL(feedbackScript.getAttribute("src"), url), {credentials: "same-origin"});
                if (!feedbackResponse.ok) throw new Error("Password feedback unavailable");
                feedbackSource = await feedbackResponse.text();
            }
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
            if (nextRoot.dataset.startStep) root.dataset.startStep = nextRoot.dataset.startStep;
            else delete root.dataset.startStep;
            root.dataset.authView = view;
            frame.className = nextRoot.querySelector(".eo-auth-frame").className;
            executeInitializer(initializer.textContent);
            if (feedbackSource) executeInitializer(feedbackSource);
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
