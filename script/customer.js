const customerApp = document.getElementById("customer-app");
const cartForms = document.querySelectorAll("[data-cart-form]");
const saveQueue = [];
const saveErrors = {};
const afterSave = [];
let saving = false;
let ordering = customerApp && customerApp.dataset.ordering === "1";
let cartCount = customerApp ? Number(customerApp.dataset.cartCount) : 0;
let pollRunning = false;
let customerTimer;
let cartRevision = 0;
let messageTimer;
const flashMessage = document.querySelector("[data-flash].success");
if (flashMessage) window.setTimeout(function () { flashMessage.hidden = true; }, 5000);

function showCustomerMessage(text, type) {
    const message = document.getElementById("customer-message");
    if (!message) return;
    window.clearTimeout(messageTimer);
    message.className = "notice " + (type || "error");
    message.textContent = text;
    message.hidden = !text;
    if (type === "success") messageTimer = window.setTimeout(function () { message.hidden = true; }, 4000);
}

function cartData(form) {
    const data = new URLSearchParams(new FormData(form));
    data.set("quantity", form.elements.quantity.value);
    data.set("ajax", "1");
    return data.toString();
}

function saveErrorMessage(request) {
    const body = request.responseText;
    if (body.includes("/aes.js") || body.includes("This site requires Javascript to work")) {
        return "โฮสต์ขอให้ยืนยันเบราว์เซอร์ กรุณาเปิดหน้าเมนูใหม่";
    }
    const type = request.getResponseHeader("Content-Type") || "";
    if (type.startsWith("text/plain") && (request.status === 403 || request.status === 409 || request.status === 500) && body.trim()) {
        return body.trim();
    }
    return "บันทึกรายการไม่สำเร็จ (HTTP " + request.status + ")";
}

function updateCartCount(count) {
    cartCount = count;
    const counts = document.querySelectorAll(".cart-count");
    for (let i = 0; i < counts.length; i++) counts[i].textContent = count;
    const empty = document.getElementById("cart-empty");
    const orderForm = document.getElementById("order-form");
    if (empty) document.getElementById("cart-empty").style.display = count ? "none" : "block";
    if (empty) empty.hidden = count > 0;
    if (orderForm) {
        orderForm.hidden = count === 0;
        orderForm.querySelector("button[type='submit']").disabled = !ordering || count === 0;
    }
}

function updateStepper(form) {
    const quantity = Number(form.elements.quantity.value);
    const available = form.dataset.available === "1";
    const buttons = form.querySelectorAll("[data-quantity-change]");
    for (let i = 0; i < buttons.length; i++) {
        const plus = Number(buttons[i].dataset.quantityChange) > 0;
        buttons[i].disabled = !ordering || (plus ? !available || quantity >= 99 : quantity <= 0);
    }
    form.elements.quantity.disabled = !ordering || !available;
    const row = form.closest("[data-cart-item]");
    row.classList.toggle("is-selected", quantity > 0);
    const remove = form.querySelector("[data-remove-cart]");
    if (remove) remove.disabled = !ordering;
    const note = form.querySelector("textarea[name='note']");
    if (note) note.disabled = !ordering;
    const save = form.querySelector(".quantity-save");
    if (save) save.disabled = !ordering;
}

function queueCartSave(form, force) {
    if (!ordering || !form.reportValidity()) return;
    const data = cartData(form);
    if (!force && data === form.dataset.queuedValue) return;
    form.dataset.queuedValue = data;
    cartRevision++;
    form.dataset.version = String(Number(form.dataset.version || 0) + 1);
    const job = { form: form, data: data, version: form.dataset.version };
    let replaced = false;
    for (let i = 0; i < saveQueue.length; i++) {
        if (saveQueue[i].form === form) {
            saveQueue[i] = job;
            replaced = true;
            break;
        }
    }
    if (!replaced) saveQueue.push(job);
    updateStepper(form);
    runCartQueue();
}

function finishSaveCallbacks() {
    if (saving || saveQueue.length) return;
    const okay = Object.keys(saveErrors).length === 0;
    while (afterSave.length) afterSave.shift()(okay);
}

function runCartQueue() {
    if (saving || !saveQueue.length) {
        finishSaveCallbacks();
        return;
    }
    saving = true;
    const job = saveQueue.shift();
    const form = job.form;
    const id = form.elements.food_id.value;
    const status = form.querySelector("[data-save-status]");
    status.textContent = "กำลังบันทึก…";
    const request = new XMLHttpRequest();
    request.open("POST", customerApp.dataset.cartUrl);
    request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    request.timeout = 10000;

    function finish(okay, message, state) {
        saving = false;
        if (okay) {
            delete saveErrors[id];
            form.removeAttribute("data-save-error");
            updateCartCount(Number(state.dataset.cartCount));
            if (form.dataset.version === job.version) {
                form.elements.quantity.value = state.dataset.quantity;
                form.dataset.queuedValue = cartData(form);
                status.textContent = "บันทึกแล้ว";
                updateStepper(form);
                const orderForm = document.getElementById("order-form");
                if (orderForm) {
                    if (Number(state.dataset.quantity) === 0) {
                        const fields = orderForm.querySelectorAll("[data-expected-food='" + id + "']");
                        for (let i = 0; i < fields.length; i++) fields[i].remove();
                    } else {
                        const quantity = orderForm.elements["expected_quantities[" + id + "]"];
                        const note = orderForm.elements["expected_notes[" + id + "]"];
                        if (quantity && note) {
                            quantity.value = state.dataset.quantity;
                            note.value = form.elements.note.value;
                        }
                    }
                }
                if (Number(state.dataset.quantity) === 0 && form.closest(".cart-row")) {
                    form.closest(".cart-row").remove();
                }
            }
            if (!Object.keys(saveErrors).length) showCustomerMessage("");
        } else {
            saveErrors[id] = message;
            form.dataset.saveError = "1";
            status.textContent = message;
            showCustomerMessage(message);
        }
        runCartQueue();
    }

    request.onload = function () {
        const result = document.createElement("template");
        result.innerHTML = request.responseText;
        const state = result.content.querySelector("[data-cart-count][data-quantity]");
        if (request.status === 200 && state) {
            finish(true, "", state);
        } else {
            finish(false, saveErrorMessage(request));
        }
    };
    request.onerror = request.ontimeout = function () {
        finish(false, "การเชื่อมต่อขัดข้อง ยังยืนยันการบันทึกไม่ได้ กรุณากดบันทึกอีกครั้ง");
    };
    request.send(job.data);
}

function flushCart(callback) {
    for (let i = 0; i < cartForms.length; i++) {
        if (cartForms[i].isConnected) {
            if (!cartForms[i].checkValidity()) {
                cartForms[i].reportValidity();
                callback(false);
                return;
            }
            queueCartSave(cartForms[i], false);
        }
    }
    afterSave.push(callback);
    finishSaveCallbacks();
}

function applyVisitState(active, allowed) {
    ordering = active && allowed;
    const notice = document.getElementById("ordering-message");
    if (notice) {
        notice.hidden = ordering;
        notice.textContent = active ? "แจ้งเช็คบิลแล้ว กรุณารอพนักงาน" : "รอบบริการนี้สิ้นสุดแล้ว ขอบคุณที่มาใช้บริการ";
    }
    for (let i = 0; i < cartForms.length; i++) {
        if (cartForms[i].isConnected) updateStepper(cartForms[i]);
    }
    const actionForms = document.querySelectorAll("[data-customer-action]");
    for (let i = 0; i < actionForms.length; i++) {
        const action = actionForms[i].elements.action.value;
        actionForms[i].querySelector("button[type='submit']").disabled = actionForms[i].dataset.sending === "1" || !active || (action !== "help" && !ordering) || (action === "order" && !cartCount);
    }
    if (!active) window.clearInterval(customerTimer);
}

function loadCustomerStatus() {
    if (pollRunning) return;
    pollRunning = true;
    const revision = cartRevision;
    const request = new XMLHttpRequest();
    request.open("GET", customerApp.dataset.statusUrl);
    request.timeout = 10000;
    request.onload = function () {
        pollRunning = false;
        const message = document.getElementById("customer-poll-message");
        if (request.status !== 200) {
            if (message) message.textContent = "อัปเดตไม่สำเร็จ ระบบจะลองใหม่อัตโนมัติ";
            return;
        }
        const result = document.createElement("template");
        result.innerHTML = request.responseText;
        const state = result.content.querySelector("[data-active][data-ordering][data-cart-count]");
        if (!state) {
            if (message) message.textContent = saveErrorMessage(request);
            return;
        }
        const history = document.getElementById("customer-status");
        if (history) history.innerHTML = request.responseText;
        if (message) message.textContent = "";
        if (state) {
            if (revision === cartRevision && !saving && !saveQueue.length && state.hasAttribute("data-cart-count")) updateCartCount(Number(state.dataset.cartCount));
            applyVisitState(state.dataset.active === "1", state.dataset.ordering === "1");
        }
    };
    request.onerror = request.ontimeout = function () {
        pollRunning = false;
        const message = document.getElementById("customer-poll-message");
        if (message) message.textContent = "การเชื่อมต่อขัดข้อง ระบบจะลองใหม่อัตโนมัติ";
    };
    request.send();
}

if (customerApp) {
    for (let i = 0; i < cartForms.length; i++) {
        const form = cartForms[i];
        form.dataset.queuedValue = cartData(form);
        updateStepper(form);
        const buttons = form.querySelectorAll("[data-quantity-change], [data-remove-cart]");
        for (let j = 0; j < buttons.length; j++) {
            buttons[j].addEventListener("click", function () {
                if (!ordering) return;
                const change = Number(buttons[j].dataset.quantityChange || 0);
                form.elements.quantity.value = buttons[j].hasAttribute("data-remove-cart") ? 0 : Math.max(0, Math.min(99, Number(form.elements.quantity.value) + change));
                queueCartSave(form, false);
            });
        }
        form.elements.quantity.addEventListener("change", function () { queueCartSave(form, false); });
        const note = form.querySelector("textarea[name='note']");
        if (note) note.addEventListener("input", function () {
            const id = form.elements.food_id.value;
            const counter = document.getElementById("note-count-" + id);
            counter.textContent = note.value.length + "/200 ตัวอักษร";
            document.getElementById("note-count-" + id).style.color = note.value.length >= 180 ? "#b42318" : "#667085";
            queueCartSave(form, false);
        });
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            queueCartSave(form, true);
        });
    }

    document.documentElement.classList.add("js");

    const category = document.getElementById("category-filter");
    if (category) category.addEventListener("change", function () {
        const sections = document.querySelectorAll(".menu-category-section");
        for (let i = 0; i < sections.length; i++) sections[i].hidden = category.value !== "" && sections[i].dataset.category !== category.value;
    });

    document.addEventListener("click", function (event) {
        const link = event.target.closest("a[data-customer-link]");
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        flushCart(function (okay) {
            if (okay) window.location.href = link.href;
            else showCustomerMessage("มีรายการที่ยังบันทึกไม่สำเร็จ กรุณาแก้ไขหรือกดบันทึกอีกครั้ง");
        });
    });

    document.addEventListener("submit", function (event) {
        const form = event.target;
        if (!form.hasAttribute("data-customer-action")) return;
        event.preventDefault();
        flushCart(function (okay) {
            if (!okay) {
                showCustomerMessage("มีรายการที่ยังบันทึกไม่สำเร็จ กรุณาแก้ไขก่อนทำรายการต่อ");
                return;
            }
            const action = form.elements.action.value;
            if (action === "help") {
                const request = new XMLHttpRequest();
                const data = new URLSearchParams(new FormData(form));
                data.set("ajax", "1");
                form.dataset.sending = "1";
                const button = form.querySelector("button[type='submit']");
                button.disabled = true;
                request.open("POST", customerApp.dataset.cartUrl);
                request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                request.timeout = 10000;
                request.onload = function () {
                    form.removeAttribute("data-sending");
                    button.disabled = false;
                    const okay = request.status === 200 && request.responseText.trim() === "เรียกพนักงานแล้ว กรุณารอสักครู่";
                    showCustomerMessage(okay ? request.responseText : saveErrorMessage(request), okay ? "success" : "error");
                    if (okay) loadCustomerStatus();
                };
                request.onerror = request.ontimeout = function () {
                    form.removeAttribute("data-sending");
                    button.disabled = false;
                    showCustomerMessage("การเชื่อมต่อขัดข้อง กรุณาลองเรียกพนักงานอีกครั้ง");
                };
                request.send(data.toString());
                return;
            }
            if (action === "bill") {
                const question = cartCount ? "มีอาหารที่ยังไม่ส่ง " + cartCount + " ที่ ต้องการลบอาหารในตะกร้าแล้วเรียกเช็คบิลหรือไม่?" : "ต้องการเรียกเช็คบิลหรือไม่? หลังจากนี้จะสั่งอาหารเพิ่มไม่ได้";
                if (!window.confirm(question)) return;
                form.elements.discard_cart.value = "1";
            }
            if (action === "order" && !cartCount) return;
            form.querySelector("button[type='submit']").disabled = true;
            HTMLFormElement.prototype.submit.call(form);
        });
    });

    window.addEventListener("beforeunload", function (event) {
        if (saving || saveQueue.length || Object.keys(saveErrors).length) {
            event.preventDefault();
            event.returnValue = "";
        }
    });
    updateCartCount(cartCount);
    customerTimer = window.setInterval(loadCustomerStatus, 5000);
    loadCustomerStatus();
}

document.addEventListener("error", function (event) {
    const image = event.target;
    if (image.tagName === "IMG" && image.dataset.fallback) {
        const fallback = image.dataset.fallback;
        image.removeAttribute("data-fallback");
        image.src = fallback;
    }
}, true);
