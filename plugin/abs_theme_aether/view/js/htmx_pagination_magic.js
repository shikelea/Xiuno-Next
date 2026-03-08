// HTMX翻页器动作

function renderPagination(paginationData) {
    if (paginationData.pageItems === 0) {
        return;
    }

    const paginationContainer = document.querySelector("ul.pagination");

    if (!paginationContainer) {
        console.error("Pagination container (ul.pagination) not found");
        return;
    }

    paginationContainer.innerHTML = '';

    function createHiddenInput(name, value) {
        const input = document.createElement('input');
        input.className = 'pagination_extra_param';
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }


    const hidden_input = createHiddenInput('IS_IN_PAGINATION', 1);
    paginationContainer.appendChild(hidden_input);

    if (paginationData.hasOwnProperty("from_threadlist") && paginationData.from_threadlist) {
        const hidden_input2 = createHiddenInput('IS_IN_THREADLIST', 1);
        paginationContainer.appendChild(hidden_input2);
    }

    if (paginationData.hasOwnProperty("from_postlist") && paginationData.from_postlist) {
        const hidden_input2 = createHiddenInput('IS_IN_POSTLIST', 1);
        paginationContainer.appendChild(hidden_input2);
    }

    if (paginationData.hasOwnProperty("from_noticelist") && paginationData.from_noticelist) {
        const hidden_input2 = createHiddenInput('IS_IN_NOTICELIST', 1);
        paginationContainer.appendChild(hidden_input2);
    }

    myForEach(paginationData.pageItems, (item) => {
        const { text, url } = item;
        const isActive = text === paginationData.active;
        const activeClass = isActive ? 'active' : '';

        const li = document.createElement('li');
        li.className = `page-item ${activeClass}`;
        li.setAttribute('data-active', text);

        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = url;
        a.innerHTML = text === '<' ? '<' : (text === '>' ? '>' : text);
        a.setAttribute('hx-include', ".pagination_extra_param");

        li.appendChild(a);
        paginationContainer.appendChild(li);
    });

    if (window.htmx) {
        htmx.process(paginationContainer);
    }
}

document.body.addEventListener("updatePagination", function (evt) {
    renderPagination(evt.detail);
});