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
			const response = await fetch("customer_navigation.php",{
				credentials:"same-origin",
				cache:"no-store"
			});
			if(!response.ok) return;
			const markup = await response.text();
			if(request!==latestRequest) return;
			const updated = document.createElement("div");
			updated.innerHTML = markup;
			const orderNow = document.querySelector(".home-cta");
			if(orderNow)
			{
				orderNow.setAttribute("href",updated.querySelector('a[href="category.php"]')
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
