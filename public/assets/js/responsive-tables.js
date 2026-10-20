(function ($) {
    "use strict";

    var scope = ".aiz-main-content, .aiz-user-panel, .aiz-main-wrapper > .flex-grow-1, .modal";
    var openMenus = new Map();

    function prepare(root) {
        var tables = root.querySelectorAll("table.table");
        if (root.matches && root.matches("table.table")) {
            tables = [root].concat(Array.prototype.slice.call(tables));
        }
        Array.prototype.forEach.call(tables, function (table) {
            if (!table.closest(scope) || table.parentElement.closest("table") ||
                table.matches(".footable-details, [data-table-scroll='false']") ||
                (!table.tHead && !table.classList.contains("aiz-table"))) {
                return;
            }
            var wrapper = table.parentElement;
            if (!wrapper.classList.contains("aiz-table-scroll")) {
                if (!wrapper.className.match(/\btable-responsive(?:-[a-z]+)?\b/)) {
                    wrapper = document.createElement("div");
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
                wrapper.classList.add("aiz-table-scroll");
                wrapper.setAttribute("tabindex", "0");
            }
            Array.prototype.forEach.call(table.querySelectorAll("thead th"), function (header) {
                if (header.querySelector(".check-all, input[type='checkbox']")) {
                    header.classList.add("aiz-table-control");
                }
            });
        });
    }

    // Bootstrap/Popper retains its original trigger and menu references when portaled.
    $(document).on("show.bs.dropdown.aizTables", function (event) {
        var dropdown = event.target;
        if (!dropdown.closest(".aiz-table-scroll") || openMenus.has(dropdown)) return;
        var menu = dropdown.querySelector(".dropdown-menu");
        if (!menu) return;
        $(menu).find(".dropdown-item").each(function () {
            if (!this.matches("a[href], button, input, select, textarea, [tabindex]")) {
                this.setAttribute("tabindex", "-1");
            }
        });
        var marker = document.createComment("table dropdown");
        menu.parentNode.insertBefore(marker, menu);
        var scroller = dropdown.closest(".aiz-table-scroll");
        openMenus.set(dropdown, {menu: menu, marker: marker, scroller: scroller,
            scrollLeft: scroller.scrollLeft, scrollTop: scroller.scrollTop});
        menu.classList.add("aiz-table-dropdown-menu");
        document.body.appendChild(menu);
    });

    $(document).on("hidden.bs.dropdown.aizTables", function (event) {
        var state = openMenus.get(event.target);
        if (!state) return;
        state.menu.classList.remove("aiz-table-dropdown-menu");
        if (state.marker.parentNode) state.marker.parentNode.replaceChild(state.menu, state.marker);
        else state.menu.remove();
        openMenus.delete(event.target);
    });

    function closeMenus(scroller) {
        openMenus.forEach(function (state, dropdown) {
            if (scroller && (state.scroller !== scroller ||
                (state.scrollLeft === scroller.scrollLeft && state.scrollTop === scroller.scrollTop))) return;
            var trigger = dropdown.querySelector('[data-toggle="dropdown"]');
            if (trigger && state.menu.classList.contains("show")) $(trigger).dropdown("toggle");
        });
    }
    document.addEventListener("scroll", function (event) {
        if (event.target.classList && event.target.classList.contains("aiz-table-scroll")) closeMenus(event.target);
    }, true);
    window.addEventListener("resize", function () { closeMenus(); });

    // Portaled menus no longer have Bootstrap's expected dropdown ancestor.
    document.addEventListener("keydown", function (event) {
        if (["Escape", "ArrowDown", "ArrowUp", "Home", "End"].indexOf(event.key) === -1 ||
            event.target.matches("input, textarea, select")) return;
        var state, dropdown, trigger;
        openMenus.forEach(function (entry, owner) {
            if (entry.menu.contains(event.target)) { state = entry; dropdown = owner; }
        });
        if (!state) {
            trigger = event.target.closest('[data-toggle="dropdown"]');
            if (!trigger || !trigger.closest(".aiz-table-scroll") ||
                ["ArrowDown", "ArrowUp"].indexOf(event.key) === -1) return;
            dropdown = trigger.parentElement;
            if (!openMenus.has(dropdown)) $(trigger).dropdown("toggle");
            state = openMenus.get(dropdown);
            if (!state) return;
        }
        trigger = trigger || dropdown.querySelector('[data-toggle="dropdown"]');
        event.preventDefault();
        event.stopImmediatePropagation();
        if (event.key === "Escape") {
            $(trigger).dropdown("toggle");
            trigger.focus();
            return;
        }
        var items = $(state.menu).find(".dropdown-item:not(.disabled):not(:disabled)").filter(":visible").toArray();
        var index = items.indexOf(document.activeElement);
        if (event.key === "Home") index = 0;
        else if (event.key === "End") index = items.length - 1;
        else if (event.key === "ArrowUp") index = index < 0 ? items.length - 1 : Math.max(0, index - 1);
        else index = Math.min(items.length - 1, index + 1);
        if (items[index]) items[index].focus();
    }, true);

    prepare(document);
    new MutationObserver(function (changes) {
        changes.forEach(function (change) {
            Array.prototype.forEach.call(change.addedNodes, function (node) {
                if (node.nodeType === 1 && (node.matches("table.table") || node.querySelector("table.table"))) prepare(node);
            });
        });
        openMenus.forEach(function (state, dropdown) {
            if (!dropdown.isConnected) {
                state.menu.remove();
                openMenus.delete(dropdown);
            }
        });
    }).observe(document.body, {childList: true, subtree: true});
})(jQuery);
