const DEMO = {
  users: [],
  enrollments: [],
  requirements: [],
  documentRequests: [],
  grades: [],
  competencies: [],
  notifications: [],
};

function seedData() {
  // MySQL is the source of truth. Only ensure settings exists in memory if missing.
  if (window.__DIGITECH_READY__) {
    if (!DG.getData("settings", null)) {
      DG.saveData("settings", {
        theme: "light",
        teacherRegistration: true,
        adminRegistration: false,
        institutionName: "Digitech College",
      });
    }
    return;
  }

  if (!DG.getData("settings", null)) {
    DG.saveData("settings", {
      theme: "light",
      teacherRegistration: true,
      adminRegistration: false,
    });
  }
}

seedData();
