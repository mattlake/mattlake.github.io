/* Vim-style keyboard navigation.
 *
 * Only three pieces of state are client-side: the selected row on a list page,
 * whether the help overlay is open, and whether the `g` prefix is armed.
 * Everything else is routing, resolved at build time.
 */
(function () {
  "use strict";

  var ROUTES = { h: "/", b: "/blog/", d: "/devlog/", a: "/about/" };

  var rows = Array.prototype.slice.call(document.querySelectorAll("[data-row]"));
  var help = document.querySelector("[data-help]");
  var pending = document.querySelector("[data-pending]");
  var posCell = document.querySelector("[data-pos]");
  var helpToggle = document.querySelector("[data-help-toggle]");

  var sel = rows.length ? 0 : -1;
  var gArmed = false;

  function setPending(on) {
    gArmed = on;
    if (pending) pending.hidden = !on;
  }

  function setHelp(open) {
    if (!help) return;
    help.hidden = !open;
  }

  function helpOpen() {
    return help && !help.hidden;
  }

  function select(i) {
    if (!rows.length) return;
    var n = rows.length;
    sel = ((i % n) + n) % n;
    rows.forEach(function (row, idx) {
      row.classList.toggle("is-sel", idx === sel);
    });
    rows[sel].scrollIntoView({ block: "nearest" });
    if (posCell) posCell.textContent = sel + 1 + "/" + n;
  }

  if (help) {
    help.addEventListener("click", function () { setHelp(false); });
  }
  if (helpToggle) {
    helpToggle.addEventListener("click", function () { setHelp(!helpOpen()); });
  }
  // Clicking a row should also make it the selection, so keyboard and mouse agree.
  rows.forEach(function (row, idx) {
    row.addEventListener("mouseenter", function () { select(idx); });
  });

  window.addEventListener("keydown", function (e) {
    if (e.metaKey || e.ctrlKey || e.altKey) return;

    var t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;

    var k = e.key;

    if (k === "Escape") {
      setHelp(false);
      setPending(false);
      // Leaving a post returns to its index; the body carries the parent section.
      var back = document.querySelector(".back");
      if (back) window.location.href = back.getAttribute("href");
      return;
    }

    if (k === "?") {
      setHelp(!helpOpen());
      setPending(false);
      return;
    }

    // A non-matching key after `g` clears the prefix and is consumed, so a
    // half-typed binding can never fire later.
    if (gArmed) {
      setPending(false);
      if (ROUTES[k]) {
        e.preventDefault();
        window.location.href = ROUTES[k];
      }
      return;
    }

    if (k === "g") {
      setPending(true);
      return;
    }

    if (!rows.length) return;

    if (k === "j" || k === "ArrowDown") {
      e.preventDefault();
      select(sel + 1);
    } else if (k === "k" || k === "ArrowUp") {
      e.preventDefault();
      select(sel - 1);
    } else if (k === "Enter") {
      e.preventDefault();
      if (rows[sel]) window.location.href = rows[sel].getAttribute("href");
    }
  });
})();
