/**
 Script for kursoversikt.
 */

function openTab(evt, locName) {
	// Declare all variables
	var i, tabpanel, tab;

	// Get all elements with class="tabpanel" and hide them
	tabpanel = document.getElementsByClassName("tabpanel");
	for (i = 0; i < tabpanel.length; i++) {
		tabpanel[i].setAttribute("hidden", "hidden" );
	}

	// Get all elements with class="tab", remove the class "active", set aria-selected to false and tabindex to -1
	tab = document.getElementsByClassName("tab");
	for (i = 0; i < tab.length; i++) {
		tab[i].className = tab[i].className.replace(" active", "");
		tab[i].setAttribute("aria-selected", "false");
		tab[i].setAttribute("tabindex", "-1");
	}

	// Show the current tab, add an "active" class to the button that opened the tab, set aria-selected to true and remove tabindex
	document.getElementById(locName).removeAttribute( "hidden" );
	evt.currentTarget.className += " active";
	evt.currentTarget.setAttribute("aria-selected", "true");
	evt.currentTarget.removeAttribute("tabindex");
}
