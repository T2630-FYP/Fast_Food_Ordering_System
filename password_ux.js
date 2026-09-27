// Add immediate strength and confirmation feedback without replacing server-side validation.
(function()
{
	"use strict";

	function strengthResult(value,minLength)
	{
		if(value.length===0)
		{
			return {state:"empty",label:"Use "+minLength+" or more characters."};
		}

		let score=0;
		if(value.length>=minLength) score++;
		if(value.length>=12) score++;
		if(/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
		if(/\d/.test(value)) score++;
		if(/[^A-Za-z0-9]/.test(value)) score++;

		if(value.length<minLength || score<=2)
		{
			return {state:"weak",label:"Password strength: Weak"};
		}
		if(score<=4)
		{
			return {state:"fair",label:"Password strength: Fair"};
		}
		return {state:"strong",label:"Password strength: Strong"};
	}

	function updateStrength(input)
	{
		const output=document.getElementById(input.dataset.passwordStrength);
		if(!output) return;
		const result=strengthResult(input.value,Number(input.minLength)||8);
		output.dataset.state=result.state;
		output.textContent=result.label;
	}

	function updateMatch(confirmInput)
	{
		const source=document.getElementById(confirmInput.dataset.passwordConfirm);
		const output=document.getElementById(confirmInput.dataset.passwordMatch);
		if(!source || !output) return;

		if(confirmInput.value.length===0)
		{
			confirmInput.setCustomValidity("");
			output.dataset.state="empty";
			output.textContent="Enter the same password again.";
			return;
		}

		const matches=source.value===confirmInput.value;
		confirmInput.setCustomValidity(matches ? "" : "Passwords do not match.");
		output.dataset.state=matches ? "match" : "mismatch";
		output.textContent=matches ? "Passwords match." : "Passwords do not match.";
	}

	document.querySelectorAll("[data-password-strength]").forEach(function(input)
	{
		input.addEventListener("input",function()
		{
			updateStrength(input);
			document.querySelectorAll('[data-password-confirm="'+input.id+'"]').forEach(updateMatch);
		});
		updateStrength(input);
	});

	document.querySelectorAll("[data-password-confirm]").forEach(function(input)
	{
		input.addEventListener("input",function(){ updateMatch(input); });
		updateMatch(input);
	});
})();
