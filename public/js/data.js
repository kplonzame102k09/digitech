const DEMO = {
  users: [
    {
      id: "ADM-2026-X9B7GV",
      firstName: "Kim Philip",
      middleName: "De Pasion",
      lastName: "Lonzame",
      email: "kpdplonzame@digitech.edu",
      contact: "09123456789",
      employeeId: "",
      department: "",
      password: "password",
      role: "admin",
      photo: ""
    }
  ],

  enrollments: [],
  requirements: [],
  documentRequests: [],
  grades: [
  ],
  competencies: [],
  notifications: []
};

function seedData() {
  const users = DG.getData("users", []);
  const admin = DEMO.users[0];
  const exists = users.some(user =>
    String(user.id || "").toLowerCase() ===
    admin.id.toLowerCase()
  );
  if (!exists) {
    users.push(admin);
    DG.saveData("users", users);
  }
  if (!DG.getData("settings", null)) {
    DG.saveData("settings", {
      theme: "light",
      teacherRegistration: true,
      adminRegistration: false
    });
  }
  localStorage.setItem("digitech_seeded", "1");
}
seedData();

// function seedData() {
//   if (localStorage.getItem("digitech_seeded")) return;

//   Object.entries(DEMO).forEach(([key, value]) => {
//     DG.saveData(key, value);
//   });

//   DG.saveData("settings", {
//     theme: "light",
//     teacherRegistration: true,
//     adminRegistration: false
//   });

//   localStorage.setItem("digitech_seeded", "1");
// }

// seedData();