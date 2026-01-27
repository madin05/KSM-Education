export class TagsManager {
  constructor(suffix = "") {
    this.suffix = suffix;
    this.tagsInput = document.getElementById(`tagsInput${suffix}`);
    this.tags = [];
  }

  getTags() {
    if (this.tagsInput) {
      const value = this.tagsInput.value.trim();
      return value
        ? value
            .split(",")
            .map((t) => t.trim())
            .filter((t) => t)
        : [];
    }
    return [];
  }

  clearTags() {
    if (this.tagsInput) {
      this.tagsInput.value = "";
    }
  }
}
