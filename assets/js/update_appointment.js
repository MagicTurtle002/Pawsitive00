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