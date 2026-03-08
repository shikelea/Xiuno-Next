/* Adapted from http://thecodeplayer.com/walkthrough/ripple-click-effect-google-material-design */


function ripple_animate(e) {
    const parent = this.parentNode;

    if (parent.querySelectorAll(".ink").length === 0) {
        const span = document.createElement("span");
        span.classList.add("ink");
        parent.insertBefore(span, parent.firstChild);
    }
    const ink = parent.querySelectorAll(".ink")[0];
    ink.classList.remove("ripple_animate");
    if (!ink.offsetHeight && !ink.offsetWidth) {
        const d = Math.max(parent.offsetHeight, parent.offsetWidth);
        ink.style.height = `${d}px`;
        ink.style.width = `${d}px`;
    }
    const rect = parent.getBoundingClientRect();
    const offset = {
        top: rect.top + document.body.scrollTop,
        left: rect.left + document.body.scrollLeft
    }
    const x = e.pageX - offset.left - ink.offsetWidth / 2;
    const y = e.pageY - offset.top - ink.offsetHeight / 2;
    ink.style.top = `${y}px`;
    ink.style.left = `${x}px`;
    ink.classList.add("ripple_animate");
}

AetherExec(function () {
        const rippled_links = document.querySelectorAll("a.btn,button.btn");
        rippled_links.forEach(link => link.addEventListener("click", ripple_animate));
    });
