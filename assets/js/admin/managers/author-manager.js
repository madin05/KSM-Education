export class AuthorsManager {
  constructor(suffix = "") {
    this.suffix = suffix;
    this.authorsContainer = document.getElementById(
      `authorsContainer${suffix}`,
    );
    this.addAuthorBtn = document.getElementById(`addAuthorBtn${suffix}`);
    this.authorCount = 1;

    if (this.authorsContainer && this.addAuthorBtn) {
      this.init();
    }
  }

  init() {
    this.addAuthorBtn.addEventListener("click", (e) => {
      e.preventDefault();
      this.addAuthorField();
    });
  }

  addAuthorField() {
    this.authorCount++;
    const authorGroup = document.createElement("div");
    authorGroup.className = "author-input-group";
    authorGroup.innerHTML = `
            <input type="text" class="author-input" placeholder="Nama Penulis ${this.authorCount}">
            <button type="button" class="btn-remove-author">
                <i data-feather="x"></i>
            </button>
        `;
    this.authorsContainer.appendChild(authorGroup);

    const removeBtn = authorGroup.querySelector(".btn-remove-author");
    removeBtn.addEventListener("click", () =>
      this.removeAuthorField(authorGroup),
    );

    if (typeof feather !== "undefined") feather.replace();
  }

  removeAuthorField(authorGroup) {
    if (
      this.authorsContainer.querySelectorAll(".author-input-group").length <= 1
    ) {
      alert("Minimal harus ada 1 penulis!");
      return;
    }
    authorGroup.remove();
    this.authorCount--;
  }

  getAuthors() {
    const inputs = this.authorsContainer.querySelectorAll(".author-input");
    const authors = [];
    inputs.forEach((input) => {
      const value = input.value.trim();
      if (value) authors.push(value);
    });
    return authors;
  }

  clearAuthors() {
    const groups = this.authorsContainer.querySelectorAll(
      ".author-input-group",
    );
    groups.forEach((group, index) => {
      if (index > 0) group.remove();
    });
    const firstInput = this.authorsContainer.querySelector(".author-input");
    if (firstInput) firstInput.value = "";
    this.authorCount = 1;
  }
}
