/**
 * Book Donation System - JavaScript Utilities
 */

// Delete confirmation modal
let deleteId = null;
let deleteType = null;
let deleteEndpoint = null;

/**
 * Set delete ID and prepare modal
 */
function setDeleteId(id, type, endpoint) {
  deleteId = id;
  deleteType = type;
  deleteEndpoint = endpoint;
  document.getElementById("deleteConfirmText").textContent =
    `Are you sure you want to delete this ${type}? This action cannot be undone.`;
  const deleteModal = new bootstrap.Modal(
    document.getElementById("deleteModal"),
  );
  deleteModal.show();
}

/**
 * Confirm deletion
 */
function confirmDelete() {
  if (deleteId && deleteEndpoint) {
    window.location.href = deleteEndpoint + "?id=" + deleteId;
  }
}

/**
 * Live search filter for tables
 */
function setupTableFilter(inputId, tableId) {
  const searchInput = document.getElementById(inputId);
  if (!searchInput) return;

  searchInput.addEventListener("keyup", function () {
    const filter = this.value.toLowerCase();
    const table = document.getElementById(tableId);
    const rows = table
      .getElementsByTagName("tbody")[0]
      .getElementsByTagName("tr");

    Array.from(rows).forEach((row) => {
      const cells = row.getElementsByTagName("td");
      let found = false;

      Array.from(cells).forEach((cell) => {
        if (cell.textContent.toLowerCase().includes(filter)) {
          found = true;
        }
      });

      row.style.display = found ? "" : "none";
    });
  });
}

/**
 * Form validation
 */
function validateEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

function validateForm(formElement) {
  let isValid = true;
  const inputs = formElement.querySelectorAll("[required]");

  inputs.forEach((input) => {
    if (!input.value.trim()) {
      input.classList.add("is-invalid");
      isValid = false;
    } else {
      input.classList.remove("is-invalid");
    }
  });

  const emailInputs = formElement.querySelectorAll('input[type="email"]');
  emailInputs.forEach((input) => {
    if (input.value && !validateEmail(input.value)) {
      input.classList.add("is-invalid");
      isValid = false;
    }
  });

  return isValid;
}

/**
 * Add form validation listeners
 */
document.addEventListener("DOMContentLoaded", function () {
  const forms = document.querySelectorAll('form[data-validate="true"]');
  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      if (!validateForm(this)) {
        e.preventDefault();
        alert("Please fill in all required fields correctly.");
      }
    });
  });

  // Initialize popovers and tooltips
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]'),
  );
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Initialize popovers
  const popoverTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="popover"]'),
  );
  popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
  });
});

/**
 * Toggle password visibility
 */
function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  const icon = event.target;

  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("lni-eye-close");
    icon.classList.add("lni-eye");
  } else {
    input.type = "password";
    icon.classList.remove("lni-eye");
    icon.classList.add("lni-eye-close");
  }
}

/**
 * Show toast notification
 */
function showToast(message, type = "success") {
  const toastHTML = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-${type} text-white">
                <strong class="me-auto">${type.toUpperCase()}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;

  const toastContainer = document.createElement("div");
  toastContainer.className = "position-fixed bottom-0 end-0 p-3";
  toastContainer.innerHTML = toastHTML;
  document.body.appendChild(toastContainer);

  const toast = new bootstrap.Toast(toastContainer.querySelector(".toast"));
  toast.show();
}

/**
 * AJAX form submission with validation
 */
function submitFormAjax(formId, endpoint) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validateForm(this)) {
      alert("Please fill in all required fields correctly.");
      return;
    }

    const formData = new FormData(this);

    fetch(endpoint, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showToast(
            data.message || "Operation completed successfully!",
            "success",
          );
          if (data.redirect) {
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 1500);
          }
        } else {
          showToast(data.message || "An error occurred", "danger");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showToast("An error occurred. Please try again.", "danger");
      });
  });
}

/**
 * Debounce function for search
 */
function debounce(func, delay) {
  let timeoutId;
  return function (...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => func.apply(this, args), delay);
  };
}

/**
 * Format date
 */
function formatDate(dateString) {
  const options = {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  };
  return new Date(dateString).toLocaleDateString("en-US", options);
}
