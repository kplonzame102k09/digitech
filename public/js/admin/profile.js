(() => {
  const U = AUTH.requireRole("admin");
  if (!U) return;

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const text = (sel, value, root = document) => {
    const el = $(sel, root);
    if (el) el.textContent = value ?? "";
  };
  const fullName = (user = U) =>
    `${user?.firstName || ""} ${user?.middleName || ""} ${user?.lastName || ""}`.trim() || user?.id || "Admin";
  const showError = (message) => {
    APP?.toast?.(message, "error");
    console.error(message);
  };
  const showLoading = (button) => {
    if (button) {
      button.disabled = true;
      button.classList.add("opacity-60", "pointer-events-none");
    }
  };
  const hideLoading = (button) => {
    if (button) {
      button.disabled = false;
      button.classList.remove("opacity-60", "pointer-events-none");
    }
  };

  async function renderProfile() {
    try {
      const profileResponse = await API.admin.profile.get();
      if (profileResponse.ok && profileResponse.user) {
        Object.assign(U, profileResponse.user);
        DG.setCurrentUser(U);
      }
    } catch (error) {
      showError("Failed to load profile data");
    }

    text("#profileName", fullName());
    text("#profileId", U.user_id || U.id);
    text("#profileRole", "Administrator");
    const email = $("#email");
    if (email) email.value = U.email || "";
    ["contact", "address"].forEach((id) => {
      const el = $(`#${id}`);
      if (el) el.value = U[id] || "";
    });

    $("#profilePhotoInput")?.addEventListener("change", async (event) => {
      const file = event.target.files?.[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        APP?.toast?.("Photo must be 2 MB or smaller", "error");
        return;
      }

      try {
        await DG.uploadProfilePhoto(file);
        DG.loadProfileElements();
        APP?.toast?.("Profile photo updated");
      } catch (error) {
        showError("Failed to upload photo: " + error.message);
      } finally {
        event.target.value = "";
      }
    });

    $("#profileForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const submitButton = event.target.querySelector('button[type="submit"]');
      showLoading(submitButton);

      try {
        const payload = {
          firstName: U.firstName,
          lastName: U.lastName,
          middleName: U.middleName,
          contact: $("#contact")?.value || U.contact,
          email: $("#email")?.value || U.email,
          address: $("#address")?.value || U.address,
        };

        const response = await API.admin.profile.update(payload);

        if (response.ok) {
          Object.assign(U, response.user);
          DG.setCurrentUser(U);
          APP?.toast?.("Profile saved");
          text("#profileName", fullName());
          DG.loadProfileElements();
        } else {
          showError(response.error || "Failed to save profile");
        }
      } catch (error) {
        showError("Failed to save profile: " + error.message);
      } finally {
        hideLoading(submitButton);
      }
    });
  }

  APP.applyTheme();
  APP.updateNotif();
  DG.loadProfileElements();
  $("#open")?.addEventListener("click", () =>
    $("#side")?.classList.toggle("-translate-x-full"),
  );
  $$("[data-theme-toggle]").forEach((button) => {
    if (button.dataset.themeBound) return;
    button.dataset.themeBound = "1";
    button.addEventListener("click", () => APP.toggleTheme());
  });

  renderProfile();
  lucide.createIcons();
})();