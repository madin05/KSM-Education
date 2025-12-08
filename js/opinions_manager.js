class OpinionManager {
  constructor() {
    this.opinions = this.loadOpinions();
    console.log(
      "OpinionManager initialized with " + this.opinions.length + " opinions"
    );
  }

  loadOpinions() {
    const stored = localStorage.getItem("opinions");
    if (stored) {
      try {
        return JSON.parse(stored);
      } catch (e) {
        console.error("Error parsing opinions:", e);
        return [];
      }
    }
    return [];
  }

  saveOpinions() {
    localStorage.setItem("opinions", JSON.stringify(this.opinions));
    console.log("Opinions saved: " + this.opinions.length + " total");
    window.dispatchEvent(new Event("opinions:changed"));
  }

  async addOpinion(opinionData) {
    const capitalize = (str) => {
      return str
        .toLowerCase()
        .split(" ")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
    };

    console.log("Adding opinion:", opinionData.judulJurnal);

    const newOpinion = {
      id: Date.now(),
      kategori: "opini",
      coverImage:
        opinionData.coverImage ||
        "https://via.placeholder.com/150x200/4a5568/ffffff?text=No+Cover",
      date: this.getCurrentDate(),
      title: capitalize(opinionData.judulJurnal),
      description: capitalize(opinionData.abstrak.substring(0, 100)) + "...",
      fileName: opinionData.fileName,
      fileData: opinionData.fileData,
      author: opinionData.namaPenulis.map((a) => capitalize(a)),
      email: opinionData.email,
      contact: opinionData.kontak,
      fullAbstract: capitalize(opinionData.abstrak),
      tags: opinionData.tags || [], // Tambah ini
    };

    this.opinions.unshift(newOpinion);
    this.saveOpinions();

    console.log("Opinion added successfully!");
    console.log("Total opinions now: " + this.opinions.length);
  }

  deleteOpinion(id) {
    const index = this.opinions.findIndex((o) => o.id === id);
    if (index > -1) {
      const deleted = this.opinions.splice(index, 1)[0];
      this.saveOpinions();
      console.log("Opinion deleted:", deleted.title);
      return true;
    }
    return false;
  }

  getOpinionById(id) {
    return this.opinions.find((o) => o.id === id) || null;
  }

  updateOpinion(id, updatedData) {
    const capitalize = (str) => {
      return str
        .toLowerCase()
        .split(" ")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
    };

    const index = this.opinions.findIndex((o) => o.id === id);
    if (index > -1) {
      this.opinions[index] = {
        ...this.opinions[index],
        title: capitalize(updatedData.title),
        author: Array.isArray(updatedData.author)
          ? updatedData.author.map((a) => capitalize(a))
          : [capitalize(updatedData.author)],
        fullAbstract: capitalize(updatedData.fullAbstract),
        description: capitalize(updatedData.description),
        email: updatedData.email,
        contact: updatedData.contact,
      };
      this.saveOpinions();
      console.log("Opinion updated:", this.opinions[index].title);
      return true;
    }
    return false;
  }

  getCurrentDate() {
    const days = [
      "MINGGU",
      "SENIN",
      "SELASA",
      "RABU",
      "KAMIS",
      "JUMAT",
      "SABTU",
    ];
    const months = [
      "JANUARY",
      "FEBRUARY",
      "MARCH",
      "APRIL",
      "MAY",
      "JUNE",
      "JULY",
      "AUGUST",
      "SEPTEMBER",
      "OCTOBER",
      "NOVEMBER",
      "DECEMBER",
    ];

    const now = new Date();
    const dayName = days[now.getDay()];
    const day = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();

    return dayName + " - " + day + " " + month + " " + year;
  }
}

console.log("opinions_manager.js loaded");
