window.addEventListener('DOMContentLoaded', show, false);

function init() {
	blur();
	setTimeout(show, 10000);
}

function blur() {
	document.getElementById('content'  ).style.display = 'none';
	document.getElementById('loader'   ).style.display = 'block';
	document.getElementById('load-text').style.display = 'block';
}

function show() {
	document.getElementById('load-taxt').style.display = 'none';
	document.getElementById('loader'   ).style.display = 'none';
	document.getElementById('content'  ).style.display = 'block';
}
