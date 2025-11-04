document.addEventListener("DOMContentLoaded", function () {
  const doctorInput = document.getElementById("selected_doctor_id"); // hidden input
  const dateInput = document.getElementById("appt_date_input");
  const timeslotBtns = () =>
    Array.from(document.querySelectorAll(".timeslot-btn"));
  const hiddenTimeslotInput =
    document.getElementById("selected_timeslot") ||
    document.querySelector('input[name="appt_time"]');

  function refreshTimeslots() {
    const doctorId = doctorInput && doctorInput.value;
    const date = dateInput && dateInput.value;
    if (!doctorId || !date) {
      console.log("missing data");
      return;
    }

    // If we're rescheduling, send the appointment being edited
    const updateIdEl = document.querySelector('input[name="update_id"]');
    const currentUpdateId = updateIdEl ? parseInt(updateIdEl.value, 10) : null;

    const params = new URLSearchParams({
      doctor: String(doctorId),
      date: String(date),
    });
    if (currentUpdateId) params.set("current_appt_id", String(currentUpdateId));

    fetch(`appointment.php?${params.toString()}`)
      .then((r) => r.json())
      .then((booked) => {
        const map = {};
        if (Array.isArray(booked))
          booked.forEach((b) => {
            map[b.time] = b;
          });

        timeslotBtns().forEach((btn) => {
          const slot = btn.dataset.time || btn.textContent.trim();
          btn.classList.remove("booked", "your-booking", "selected");
          btn.disabled = false;

          const info = map[slot];
          if (!info) return; // free slot

          if (info.is_mine) {
            // patient’s own slot OR doctor’s current reschedule slot
            btn.classList.add("your-booking", "selected");
            if (hiddenTimeslotInput) hiddenTimeslotInput.value = slot;
          } else {
            // booked by someone else -> disable
            btn.classList.add("booked");
            btn.disabled = true;
          }
        });
      })
      .catch((err) => console.error("Failed to fetch booked slots", err));
  }

  // hook up date change and doctor selection clicks
  if (dateInput) dateInput.addEventListener("change", refreshTimeslots);

  // clicking timeslot: set selected value (but don't allow clicking disabled ones)
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".timeslot-btn");
    if (!btn) return;
    if (btn.disabled) return;
    // deselect others
    timeslotBtns().forEach((b) => b.classList.remove("selected"));
    btn.classList.add("selected");
    if (hiddenTimeslotInput)
      hiddenTimeslotInput.value = btn.dataset.time || btn.textContent.trim();
  });

  // initial refresh on load (if date is pre-filled)
  refreshTimeslots();
});

// === DOCTOR DROPDOWN LOGIC ===
(function () {
  const selector = document.querySelector(".doctor-selector");
  if (!selector) return;

  const dropdown = selector.querySelector(".dropdown-mock");
  const doctorList = selector.querySelector(".doctor-list");
  const hiddenInput = document.getElementById("selected_doctor_id");
  const contentWrapper = selector.querySelector(".dropdown-content-wrapper");

  // If the dropdown is disabled (doctor logged in), don't allow opening.
  const isDisabled = dropdown && dropdown.classList.contains("disabled");

  // Open/close the list
  if (dropdown && !isDisabled) {
    dropdown.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      selector.classList.toggle("open");
    });

    // Close when clicking outside
    document.addEventListener("click", (e) => {
      if (!selector.contains(e.target)) selector.classList.remove("open");
    });

    // Keyboard: Enter/Space to open, Escape to close
    dropdown.tabIndex = 0;
    dropdown.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        selector.classList.toggle("open");
      } else if (e.key === "Escape") {
        selector.classList.remove("open");
      }
    });
  }

  // Pick a doctor from the list
  if (doctorList) {
    doctorList.addEventListener("click", (e) => {
      const item = e.target.closest(".doctor-item");
      if (!item) return;

      const doctorId = item.getAttribute("data-doctor-id");
      if (!doctorId || !hiddenInput) return;

      // Update hidden input (used by availability fetch + form submit)
      hiddenInput.value = doctorId;

      // Update the display inside the dropdown header
      const nameEl = item.querySelector(".doctor-name");
      const specEl = item.querySelector(".doctor-specialty");
      const imgEl = item.querySelector("img");

      if (contentWrapper) {
        contentWrapper.innerHTML = `
          <div class="doctor-item">
            <img src="${
              imgEl
                ? imgEl.getAttribute("src")
                : "assets/images/default-avatar.png"
            }" alt="${nameEl ? nameEl.textContent : "Doctor"}">
            <div class="doctor-info">
              <span class="doctor-name">${
                nameEl ? nameEl.textContent : "Selected Doctor"
              }</span>
              <span class="doctor-specialty">${
                specEl ? specEl.textContent : ""
              }</span>
            </div>
          </div>
        `;
      }

      // Mark as selected, close the list
      selector.classList.add("has-selection");
      selector.classList.remove("open");

      // Refresh timeslots for the newly selected doctor
      if (typeof refreshTimeslots === "function") {
        refreshTimeslots();
      } else {
        // If refreshTimeslots is scoped, re-trigger via date input change
        const dateInput = document.getElementById("appt_date_input");
        if (dateInput) {
          const ev = new Event("change", { bubbles: true });
          dateInput.dispatchEvent(ev);
        }
      }
    });
  }
})();
