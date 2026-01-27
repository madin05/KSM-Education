export class PengurusManager {
  constructor(suffix = "") {
    this.suffix = suffix;
    this.pengurusContainer = document.getElementById(
      `pengurusContainer${suffix}`,
    );
    this.addPengurusBtn = document.getElementById(`addPengurusBtnJurnal`);
    this.pengurusCount = 1;

    if (this.pengurusContainer && this.addPengurusBtn) {
      this.init();
    } else {
      console.warn(
        `PengurusManager: Elements not found for suffix "${suffix}"`,
      );
    }
  }

  init() {
    this.addPengurusBtn.addEventListener("click", (e) => {
      e.preventDefault();
      this.addPengurusField();
    });
  }

  addPengurusField() {
    this.pengurusCount++;
    const pengurusGroup = document.createElement("div");
    pengurusGroup.className = "pengurus-input-group";
    pengurusGroup.dataset.pengurusIndex = this.pengurusCount - 1;
    pengurusGroup.innerHTML = `
            <input type="text" class="pengurus-input" placeholder="Nama Pengurus ${this.pengurusCount}">
            <button type="button" class="btn-remove-pengurus">
                <i data-feather="x"></i>
            </button>
        `;

    this.pengurusContainer.appendChild(pengurusGroup);

    const removeBtn = pengurusGroup.querySelector(".btn-remove-pengurus");
    removeBtn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.removePengurusField(pengurusGroup);
    });

    if (typeof feather !== "undefined") feather.replace();
    this.updatePlaceholders();
  }

  removePengurusField(pengurusGroup) {
    const pengurusGroups = this.pengurusContainer.querySelectorAll(
      ".pengurus-input-group",
    );
    if (pengurusGroups.length <= 1) {
      alert("Minimal harus ada 1 pengurus!");
      return;
    }

    pengurusGroup.remove();
    this.pengurusCount--;
    this.updatePlaceholders();
  }

  updatePlaceholders() {
    const pengurusInputs =
      this.pengurusContainer.querySelectorAll(".pengurus-input");
    pengurusInputs.forEach((input, index) => {
      input.placeholder = `Nama Pengurus ${index + 1}`;
      if (index === 0) input.required = true;
    });

    const removeButtons = this.pengurusContainer.querySelectorAll(
      ".btn-remove-pengurus",
    );
    removeButtons.forEach((btn, index) => {
      btn.style.display =
        index === 0 && pengurusInputs.length === 1 ? "none" : "flex";
    });
  }

  getPengurus() {
    const pengurusInputs =
      this.pengurusContainer.querySelectorAll(".pengurus-input");
    const pengurus = [];
    pengurusInputs.forEach((input) => {
      const value = input.value.trim();
      if (value) pengurus.push(value);
    });
    return pengurus;
  }

  clearPengurus() {
    const pengurusGroups = this.pengurusContainer.querySelectorAll(
      ".pengurus-input-group",
    );
    pengurusGroups.forEach((group, index) => {
      if (index > 0) group.remove();
    });

    const firstInput = this.pengurusContainer.querySelector(".pengurus-input");
    if (firstInput) firstInput.value = "";
    this.pengurusCount = 1;
    this.updatePlaceholders();
  }
}
