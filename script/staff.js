const staffPolls = document.querySelectorAll("[data-staff-poll]");
const flashMessage = document.querySelector("[data-flash].success");
if (flashMessage) window.setTimeout(function () { flashMessage.hidden = true; }, 5000);
const tableList = document.querySelector("[data-table-list]");
let staffActionBusy = false;
let tableStateLoading = false;

function refreshStaffSection(section) {
    if (section.dataset.loading === "1" || staffActionBusy) return;
    section.dataset.loading = "1";
    const request = new XMLHttpRequest();
    request.open("GET", section.dataset.staffPoll);
    request.timeout = 10000;
    request.onload = function () {
        section.dataset.loading = "0";
        const message = document.querySelector("[data-poll-message]");
        if (request.status === 401 || request.status === 403) {
            if (message) message.textContent = "การเข้าสู่ระบบหรือสิทธิ์ใช้งานเปลี่ยนไป กรุณาเข้าสู่ระบบอีกครั้ง";
            window.clearInterval(staffTimer);
            return;
        }
        if (request.status === 200) {
            // ไม่สร้างปุ่มใหม่ถ้าข้อมูลเดิม และไม่แย่ง focus ขณะพนักงานกำลังใช้งาน
            if (!staffActionBusy && !section.contains(document.activeElement) && section.staffResponse !== request.responseText) {
                section.innerHTML = request.responseText;
                section.staffResponse = request.responseText;
            }
            if (message) message.textContent = "";
        } else if (message) {
            message.textContent = "อัปเดตไม่สำเร็จ ระบบจะลองใหม่อัตโนมัติ";
        }
    };
    request.onerror = request.ontimeout = function () {
        section.dataset.loading = "0";
        const message = document.querySelector("[data-poll-message]");
        if (message) message.textContent = "การเชื่อมต่อขัดข้อง ระบบจะลองใหม่อัตโนมัติ";
    };
    request.send();
}

function refreshTableState() {
    if (!tableList || tableStateLoading || staffActionBusy) return;
    tableStateLoading = true;
    const request = new XMLHttpRequest();
    request.open("GET", tableList.dataset.tableStateUrl);
    request.timeout = 10000;
    request.onload = function () {
        tableStateLoading = false;
        if (request.status !== 200 || staffActionBusy) return;
        const holder = document.createElement("div");
        holder.innerHTML = request.responseText;
        const latest = holder.querySelectorAll(".table-card");
        const current = tableList.querySelectorAll(".table-card");
        let changed = latest.length !== current.length;
        for (let i = 0; i < latest.length && !changed; i++) {
            if (latest[i].id !== current[i].id || latest[i].dataset.version !== current[i].dataset.version) changed = true;
        }
        const notice = document.querySelector("[data-table-refresh]");
        if (notice) notice.hidden = !changed;
        // แจ้งให้กดอัปเดตเอง จึงไม่ล้างจำนวนคนที่กำลังกรอกในโต๊ะอื่น
    };
    request.onerror = request.ontimeout = function () { tableStateLoading = false; };
    request.send();
}

function saveTableForm(form, submitter) {
    const card = form.closest(".table-card");
    const message = card.querySelector("[data-table-message]");
    if (staffActionBusy) {
        message.textContent = "กำลังบันทึก กรุณารอสักครู่";
        message.className = "notice warning";
        message.hidden = false;
        return;
    }
    // เก็บค่าก่อนปิดปุ่ม เพื่อไม่ให้ name/value ของปุ่มที่กดหายไป
    const data = new URLSearchParams(new FormData(form));
    if (submitter && submitter.name) data.set(submitter.name, submitter.value);
    data.set("ajax", "1");
    const buttons = form.querySelectorAll("button[type='submit']");
    for (let i = 0; i < buttons.length; i++) buttons[i].disabled = true;
    staffActionBusy = true;
    message.textContent = "กำลังบันทึก…";
    message.className = "notice";
    message.hidden = false;

    const request = new XMLHttpRequest();
    request.open("POST", form.getAttribute("action"));
    request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
    request.timeout = 10000;
    function finish() {
        staffActionBusy = false;
        for (let i = 0; i < buttons.length; i++) buttons[i].disabled = false;
    }
    function fail(text) {
        finish();
        message.textContent = text;
        message.className = "notice error";
        message.hidden = false;
    }
    request.onload = function () {
        if (request.status !== 200) {
            fail(request.responseText.trim() || "บันทึกไม่สำเร็จ กรุณาลองใหม่");
            return;
        }
        const holder = document.createElement("div");
        holder.innerHTML = request.responseText;
        const updated = holder.querySelector(".table-card");
        if (!updated || updated.id !== card.id) {
            fail("ไม่สามารถแสดงสถานะโต๊ะได้ กรุณาอัปเดตหน้าอีกครั้ง");
            return;
        }
        const focusedInside = card.contains(document.activeElement);
        finish();
        card.replaceWith(updated);
        if (focusedInside) {
            const heading = updated.querySelector("h3");
            heading.tabIndex = -1;
            heading.focus({preventScroll: true});
        }
        for (let i = 0; i < staffPolls.length; i++) refreshStaffSection(staffPolls[i]);
        refreshTableState();
    };
    request.onerror = request.ontimeout = function () {
        fail("การเชื่อมต่อขัดข้อง กรุณาตรวจสอบสถานะโต๊ะก่อนลองอีกครั้ง");
    };
    request.send(data.toString());
}

const staffTimer = window.setInterval(function () {
    for (let i = 0; i < staffPolls.length; i++) refreshStaffSection(staffPolls[i]);
    refreshTableState();
}, 5000);
for (let i = 0; i < staffPolls.length; i++) refreshStaffSection(staffPolls[i]);
refreshTableState();

document.addEventListener("submit", function (event) {
    const form = event.target;
    let question = form.dataset.confirm;
    if (event.submitter && event.submitter.dataset.confirm) question = event.submitter.dataset.confirm;
    if (question && !window.confirm(question)) {
        event.preventDefault();
        return;
    }
    if (form.hasAttribute("data-table-action")) {
        event.preventDefault();
        saveTableForm(form, event.submitter);
        return;
    }
    if (form.hasAttribute("data-food-availability")) {
        event.preventDefault();
        const button = form.querySelector("button");
        const value = form.querySelector("[name='is_available']");
        const status = form.closest("tr").querySelector(".status");
        const request = new XMLHttpRequest();
        const data = new URLSearchParams(new FormData(form));
        data.set("ajax", "1");
        button.disabled = true;
        request.open("POST", form.getAttribute("action"));
        request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
        request.timeout = 10000;
        request.onload = function () {
            button.disabled = false;
            if (request.status !== 200) {
                window.alert("บันทึกไม่สำเร็จ กรุณาลองใหม่ (" + request.status + ")");
                return;
            }
            const available = value.value === "1";
            status.textContent = available ? "พร้อมเสิร์ฟ" : "หมดชั่วคราว";
            status.className = "status " + (available ? "open" : "closed");
            value.value = available ? "0" : "1";
            button.textContent = available ? "หมด" : "พร้อมเสิร์ฟ";
        };
        request.onerror = request.ontimeout = function () {
            button.disabled = false;
            window.alert("การเชื่อมต่อขัดข้อง กรุณาลองใหม่");
        };
        request.send(data.toString());
        return;
    }
    staffActionBusy = true;
    if (form.hasAttribute("data-submit-once")) {
        if (event.submitter && event.submitter.name) {
            const submitted = document.createElement("input");
            submitted.type = "hidden";
            submitted.name = event.submitter.name;
            submitted.value = event.submitter.value;
            form.appendChild(submitted);
        }
        const buttons = form.querySelectorAll("button[type='submit']");
        for (let i = 0; i < buttons.length; i++) buttons[i].disabled = true;
    }
});

document.addEventListener("error", function (event) {
    const image = event.target;
    if (image.tagName === "IMG" && image.dataset.fallback) {
        const fallback = image.dataset.fallback;
        image.removeAttribute("data-fallback");
        image.src = fallback;
    }
}, true);
