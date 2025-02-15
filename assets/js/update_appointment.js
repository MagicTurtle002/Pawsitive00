function updateAppointmentStatus(appointmentId, status, petId) {
  if (status === "Declined") {
      // Ask for a reason when declining
      Swal.fire({
          title: "Decline Appointment",
          html: `
              <p>Please provide a reason for declining this appointment:</p>
              <textarea id="declineReason" class="swal2-textarea" placeholder="Enter reason..." required></textarea>
          `,
          icon: "warning",
          showCancelButton: true,
          confirmButtonColor: "#d33",
          cancelButtonColor: "#3085d6",
          confirmButtonText: "Decline",
          cancelButtonText: "Cancel",
          preConfirm: () => {
              const reason = document.getElementById("declineReason").value.trim();
              if (!reason) {
                  Swal.showValidationMessage("⚠️ Reason is required!");
                  return false;
              }
              return reason;
          }
      }).then((result) => {
          if (result.isConfirmed) {
              proceedWithStatusUpdate(appointmentId, status, petId, result.value);
          }
      });
  } else if (status === "Confirmed") {
      // Ask for confirmation before confirming
      Swal.fire({
          title: "Confirm Appointment?",
          text: "Are you sure you want to confirm this appointment?",
          icon: "question",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Yes, Confirm",
          cancelButtonText: "Cancel",
      }).then((result) => {
          if (result.isConfirmed) {
              proceedWithStatusUpdate(appointmentId, status, petId);
          }
      });
  } else {
      proceedWithStatusUpdate(appointmentId, status, petId);
  }
}

function proceedWithStatusUpdate(appointmentId, status, petId, reason = null) {
  const payload = {
      appointment_id: appointmentId,
      status: status,
      pet_id: petId,
  };

  if (status === "Declined" && reason) {
      payload.reason = reason; // Ensure reason is included
  }

  console.log("Sending data:", JSON.stringify(payload)); // Debugging purpose

  fetch("../public/update_appointment.php", {
      method: "POST",
      headers: {
          "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
  })
  .then(response => {
      if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
  })
  .then(data => {
      console.log("Response from PHP:", data);

      if (data.success) {
          updateAppointmentUI(appointmentId, petId, status, reason);
          Swal.fire("Success!", `Appointment status updated to ${status}.`, "success");
      } else {
          Swal.fire("Error!", data.message, "error");
      }
  })
  .catch(error => {
      console.error("Fetch error:", error);
      Swal.fire("Error!", `An error occurred: ${error.message}`, "error");
  });
}

function updateAppointmentUI(appointmentId, petId, status, reason = null) {
  const appointmentElement = document.getElementById(`appointment-${appointmentId}-${petId}`);
  if (appointmentElement) {
      const statusElement = appointmentElement.querySelector(".status");
      if (statusElement) {
          statusElement.textContent = status;
      }
      const buttonsContainer = appointmentElement.querySelector(".buttons-container");
      if (buttonsContainer) {
          buttonsContainer.innerHTML = ""; // Clear previous buttons

          if (status === "Declined" && reason) {
              buttonsContainer.innerHTML = `<p>This appointment has been declined.<br><strong>Reason:</strong> ${reason}</p>`;
          } else if (status === "Done") {
              createButton(buttonsContainer, "Invoice and Billing", "status-button", () => {
                  window.location.href = `invoice_billing_form.php?appointment_id=${appointmentId}`;
              });
          } else if (status === "Confirmed") {
              createButton(buttonsContainer, "Start Consultation", "status-button", () =>
                  promptVitalsUpdate(appointmentId, petId)
              );
              createButton(buttonsContainer, "Mark as Done", "status-button", () =>
                  updateAppointmentStatus(appointmentId, "Done", petId)
              );
          }
      }
  }
}

function createButton(container, text, className, onClick) {
  const button = document.createElement("button");
  button.textContent = text;
  button.classList.add(className);
  button.onclick = onClick;
  container.appendChild(button);
}


function openRescheduleModal(appointment, appointmentId) {
    Swal.fire({
        title: "Reschedule Appointment",
        html: `
            <div class="swal2-row">
                <label for="petName"><strong>Pet:<span class="required">*</span></strong></label>
                <input type="text" id="petName" class="swal2-input" value="${appointment.pet}" readonly>
            </div>
            
            <div class="swal2-row">
                <label for="editService"><strong>Service:<span class="required">*</span></strong></label>
                <select id="editService" class="swal2-select">${generateServiceOptions(appointment.service)}</select>
            </div>

            <div class="swal2-row">
                 <label for="newDate"><strong>New Date:<span class="required">*</span></strong></label>
                <input type="date" id="newDate" class="swal2-input" value="${appointment.date}" min="${new Date().toISOString().split('T')[0]}" required>
            </div>
            
            <div class="swal2-row">
                <label for="newTime"><strong>New Time:<span class="required">*</span></strong></label>
                <select id="newTime" class="swal2-select">${generateTimeOptions(appointment.time, appointment.date)}</select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Save Changes",
        preConfirm: () => {
            const newDate = document.getElementById("newDate").value;
            const newTime = document.getElementById("newTime").value;
            const newService = document.getElementById("editService").value;

            if (!newDate || !newTime || !newService) {
                Swal.showValidationMessage("⚠️ Please complete all fields!");
                return false;
            }

            return { appointmentId, newDate, newTime, newService };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            console.log("Rescheduling appointment:", result.value);
            rescheduleAppointment(result.value);
        }
    });

    document.getElementById("newDate").addEventListener("change", function () {
        document.getElementById("newTime").innerHTML = generateTimeOptions(null, this.value);
    });
}


function generateServiceOptions(selectedService) {
    if (!services || !Array.isArray(services)) {
        console.error("⚠️ Services list is empty or not an array:", services);
        return "<option value='' disabled>No services available</option>";
    }

    let options = services.map(service => {
        let isSelected = service.ServiceName === selectedService ? "selected" : "";
        return `<option value="${service.ServiceId}" ${isSelected}>${service.ServiceName}</option>`;
    }).join("");

    return options.length > 0 ? options : "<option value='' disabled>No services available</option>";
}

function generateTimeOptions(selectedTime, selectedDate) {
    const timeSlots = [
        "08:00:00", "08:30:00", "09:00:00", "09:30:00",
        "10:00:00", "10:30:00", "11:00:00", "11:30:00",
        "12:00:00", "12:30:00", "13:00:00", "13:30:00",
        "14:00:00", "14:30:00", "15:00:00", "15:30:00",
        "16:00:00", "16:30:00", "17:00:00"
    ];

    let options = "";
    let bookedTimes = bookedTimesByDate[selectedDate] || [];

    timeSlots.forEach(time => {
        let isSelected = time === selectedTime ? "selected" : "";
        let isDisabled = bookedTimes.includes(time) ? "disabled" : "";

        options += `<option value="${time}" ${isSelected} ${isDisabled}>${formatTime(time)}</option>`;
    });

    return options;
}

// Helper function to format time into AM/PM
function formatTime(time) {
    let [hours, minutes] = time.split(':');
    let ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${hours}:${minutes} ${ampm}`;
}

function rescheduleAppointment({ appointmentId, newDate, newTime, newService }) {
    console.log("Rescheduling Appointment:", appointmentId, newDate, newTime, newService);

    fetch("../src/reschedule_appointment.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            appointment_id: appointmentId,
            new_date: newDate,
            new_time: newTime,
            new_service: newService
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire("Success!", "Appointment has been rescheduled.", "success")
                .then(() => location.reload());
        } else {
            Swal.fire("Error!", data.message, "error");
        }
    })
    .catch(error => {
        console.error("Fetch error:", error);
        Swal.fire("Error!", "Failed to reschedule appointment.", "error");
    });
}