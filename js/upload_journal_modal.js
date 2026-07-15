// =========================================================
// UPLOAD JOURNAL/OPINION MODAL MODULE
// - Gate: cek saldo token sebelum membuka form
// - Toggle tipe Jurnal/Opini (field berubah)
// - Dynamic rows: Penulis & Pengurus
// - Preview file cover & nama file PDF
// - Submit: simulasi frontend (kurangi token, simpan ke "my_journals")
//
// // TODO backend:
// // 1. Endpoint nyata: POST /api/upload_journal.php atau
// //    /api/upload_opinion.php (multipart/form-data).
// // 2. Validasi & pemotongan token WAJIB diulang di server —
// //    JANGAN percaya saldo token dari frontend/localStorage.
// // 3. Field author/pengurus perlu disesuaikan dgn skema tabel
// //    yang dipakai backend (JSON column atau tabel relasi).
// =========================================================

(function (global) {
  const MY_JOURNALS_KEY = "ksm_my_journals";
  let currentType = "jurnal";

  function getMyJournals() {
    try {
      return JSON.parse(localStorage.getItem(MY_JOURNALS_KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function saveMyJournals(list) {
    localStorage.setItem(MY_JOURNALS_KEY, JSON.stringify(list));
  }

  function addMyJournal(entry) {
    const list = getMyJournals();
    list.unshift(entry);
    saveMyJournals(list);
  }

  global.KsmMyJournals = { getMyJournals, saveMyJournals, addMyJournal };

  function createAuthorRow(prefill = "") {
    const row = document.createElement("div");
    row.className = "ksm-upload-dynamic-row";
    row.innerHTML = `
      <input type="text" placeholder="Nama penulis" class="ksm-author-input" value="${prefill}" />
      <button type="button" class="ksm-upload-row-remove" aria-label="Hapus penulis">
        <i data-feather="x"></i>
      </button>
    `;
    row.querySelector(".ksm-upload-row-remove").addEventListener("click", () => {
      const rows = document.querySelectorAll("#ksmAuthorRows .ksm-upload-dynamic-row");
      if (rows.length > 1) row.remove();
    });
    return row;
  }

  function createPengurusRow() {
    // Reuse class asli dari journal.css: .pengurus-input-group / .pengurus-input
    const row = document.createElement("div");
    row.className = "ksm-upload-dynamic-row pengurus-input-group";
    row.innerHTML = `
      <input type="text" placeholder="Nama & jabatan pengurus" class="pengurus-input ksm-pengurus-input" />
      <button type="button" class="ksm-upload-row-remove" aria-label="Hapus pengurus">
        <i data-feather="x"></i>
      </button>
    `;
    row.querySelector(".ksm-upload-row-remove").addEventListener("click", () => {
      row.remove();
    });
    return row;
  }

  function prefillAuthorFromLoggedInUser() {
    const userEmail = sessionStorage.getItem("userEmail");
    if (!userEmail) return "";
    return userEmail.split("@")[0];
  }

  function setType(type) {
    currentType = type;
    const jurnalBtn = document.getElementById("ksmTypeJurnalBtn");
    const opiniBtn = document.getElementById("ksmTypeOpiniBtn");
    const volumeGroup = document.getElementById("ksmVolumeGroup");
    const categoryGroup = document.getElementById("ksmCategoryGroup");
    const pengurusGroup = document.getElementById("ksmPengurusGroup");

    jurnalBtn.classList.toggle("active", type === "jurnal");
    opiniBtn.classList.toggle("active", type === "opini");

    volumeGroup.style.display = type === "jurnal" ? "block" : "none";
    categoryGroup.style.display = type === "opini" ? "block" : "none";
    pengurusGroup.style.display = type === "jurnal" ? "block" : "none";
  }

  function resetForm() {
    const form = document.getElementById("ksmUploadForm");
    form.reset();

    document.getElementById("ksmAuthorRows").innerHTML = "";
    document.getElementById("ksmAuthorRows").appendChild(
      createAuthorRow(prefillAuthorFromLoggedInUser()),
    );

    document.getElementById("ksmPengurusRows").innerHTML = "";

    document.getElementById("ksmCoverFileName").textContent = "";
    document.getElementById("ksmPdfFileName").textContent = "";

    form.querySelectorAll(".ksm-upload-form-group.has-error").forEach((g) =>
      g.classList.remove("has-error"),
    );

    setType("jurnal");
    if (typeof feather !== "undefined") feather.replace();
  }

  function openUploadModal() {
    const overlay = document.getElementById("ksmUploadModal");
    if (!overlay) return;
    overlay.classList.add("active");
    if (typeof feather !== "undefined") feather.replace();
  }

  function closeUploadModal() {
    const overlay = document.getElementById("ksmUploadModal");
    if (overlay) overlay.classList.remove("active");
  }

  function checkTokenAndOpenUpload() {
    const balance =
      typeof KsmTokenWallet !== "undefined" ? KsmTokenWallet.getBalance() : 0;

    if (balance < 1) {
      if (typeof global.ksmOpenInsufficientModal === "function") {
        global.ksmOpenInsufficientModal();
      }
      return;
    }

    resetForm();
    openUploadModal();
  }

  global.ksmCheckTokenAndOpenUpload = checkTokenAndOpenUpload;
  global.ksmCloseUploadModal = closeUploadModal;

  function setFieldError(fieldEl, hasError) {
    const group = fieldEl.closest(".ksm-upload-form-group");
    if (!group) return;
    group.classList.toggle("has-error", hasError);
  }

  function validateForm() {
    let valid = true;

    const title = document.getElementById("ksmFieldTitle");
    const abstract = document.getElementById("ksmFieldAbstract");
    const email = document.getElementById("ksmFieldEmail");
    const phone = document.getElementById("ksmFieldPhone");
    const pdfInput = document.getElementById("ksmPdfInput");

    if (!title.value.trim()) {
      setFieldError(title, true);
      valid = false;
    } else setFieldError(title, false);

    if (currentType === "jurnal") {
      const volume = document.getElementById("ksmFieldVolume");
      if (!volume.value.trim()) {
        setFieldError(volume, true);
        valid = false;
      } else setFieldError(volume, false);
    } else {
      const category = document.getElementById("ksmFieldCategory");
      if (!category.value.trim()) {
        setFieldError(category, true);
        valid = false;
      } else setFieldError(category, false);
    }

    if (!abstract.value.trim()) {
      setFieldError(abstract, true);
      valid = false;
    } else setFieldError(abstract, false);

    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
    if (!emailValid) {
      setFieldError(email, true);
      valid = false;
    } else setFieldError(email, false);

    if (!phone.value.trim()) {
      setFieldError(phone, true);
      valid = false;
    } else setFieldError(phone, false);

    if (!pdfInput.files || pdfInput.files.length === 0) {
      document.getElementById("ksmPdfError").style.display = "block";
      valid = false;
    } else {
      document.getElementById("ksmPdfError").style.display = "none";
    }

    return valid;
  }

  function handleSubmit(e) {
    e.preventDefault();

    if (!validateForm()) {
      if (typeof showToast === "function") {
        showToast("Mohon lengkapi field yang wajib diisi.", "error");
      }
      return;
    }

    const submitBtn = document.getElementById("ksmUploadSubmitBtn");
    submitBtn.classList.add("loading");
    submitBtn.disabled = true;

    const authors = Array.from(
      document.querySelectorAll("#ksmAuthorRows .ksm-author-input"),
    )
      .map((i) => i.value.trim())
      .filter(Boolean);

    const pengurus = Array.from(
      document.querySelectorAll("#ksmPengurusRows .ksm-pengurus-input"),
    )
      .map((i) => i.value.trim())
      .filter(Boolean);

    const payload = {
      type: currentType,
      title: document.getElementById("ksmFieldTitle").value.trim(),
      volume: document.getElementById("ksmFieldVolume").value.trim(),
      category: document.getElementById("ksmFieldCategory").value.trim(),
      tags: document
        .getElementById("ksmFieldTags")
        .value.split(",")
        .map((t) => t.trim())
        .filter(Boolean),
      abstract: document.getElementById("ksmFieldAbstract").value.trim(),
      authors,
      pengurus,
      email: document.getElementById("ksmFieldEmail").value.trim(),
      phone: document.getElementById("ksmFieldPhone").value.trim(),
    };

    console.log("Upload payload (frontend simulation):", payload);

    // ----- TODO backend -----
    // fetch(`${window.APP_CONFIG.apiBase}/upload_${currentType === 'jurnal' ? 'journal' : 'opinion'}.php`, {
    //   method: 'POST',
    //   body: buildFormDataFromPayloadAndFiles(payload),
    // });
    // Server HARUS mengecek & memotong token user di sisi backend.

    setTimeout(() => {
      // Simulasi: kurangi token & simpan ke daftar "Jurnal Saya" berstatus Pending
      if (typeof KsmTokenWallet !== "undefined") {
        KsmTokenWallet.deduct(1);
      }

      if (typeof KsmMyJournals !== "undefined") {
        KsmMyJournals.addMyJournal({
          id: "LOCAL" + Date.now(),
          title: payload.title,
          type: payload.type,
          status: "pending", // pending | published | rejected
          createdAt: new Date().toISOString(),
        });
      }

      submitBtn.classList.remove("loading");
      submitBtn.disabled = false;

      if (typeof showToast === "function") {
        showToast(
          "Karya berhasil dikirim, menunggu review admin.",
          "success",
        );
      }

      closeUploadModal();
    }, 700);
  }

  function initDragDrop(dropEl, inputEl, fileNameEl) {
    dropEl.addEventListener("click", () => inputEl.click());

    inputEl.addEventListener("change", () => {
      if (inputEl.files && inputEl.files[0]) {
        fileNameEl.textContent = inputEl.files[0].name;
      }
    });

    ["dragover", "dragenter"].forEach((evt) => {
      dropEl.addEventListener(evt, (e) => {
        e.preventDefault();
        dropEl.classList.add("drag-over");
      });
    });

    ["dragleave", "drop"].forEach((evt) => {
      dropEl.addEventListener(evt, (e) => {
        e.preventDefault();
        dropEl.classList.remove("drag-over");
      });
    });

    dropEl.addEventListener("drop", (e) => {
      const file = e.dataTransfer.files[0];
      if (file) {
        inputEl.files = e.dataTransfer.files;
        fileNameEl.textContent = file.name;
      }
    });
  }

  function init() {
    const overlay = document.getElementById("ksmUploadModal");
    if (!overlay) return; // halaman ini tidak include modal upload

    document
      .getElementById("ksmUploadClose")
      .addEventListener("click", closeUploadModal);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeUploadModal();
    });

    document
      .getElementById("ksmTypeJurnalBtn")
      .addEventListener("click", () => setType("jurnal"));
    document
      .getElementById("ksmTypeOpiniBtn")
      .addEventListener("click", () => setType("opini"));

    document
      .getElementById("ksmAddAuthorBtn")
      .addEventListener("click", () => {
        document.getElementById("ksmAuthorRows").appendChild(createAuthorRow());
        if (typeof feather !== "undefined") feather.replace();
      });

    document
      .getElementById("ksmAddPengurusBtn")
      .addEventListener("click", () => {
        document
          .getElementById("ksmPengurusRows")
          .appendChild(createPengurusRow());
        if (typeof feather !== "undefined") feather.replace();
      });

    initDragDrop(
      document.getElementById("ksmCoverDrop"),
      document.getElementById("ksmCoverInput"),
      document.getElementById("ksmCoverFileName"),
    );
    initDragDrop(
      document.getElementById("ksmPdfDrop"),
      document.getElementById("ksmPdfInput"),
      document.getElementById("ksmPdfFileName"),
    );

    document
      .getElementById("ksmUploadForm")
      .addEventListener("submit", handleSubmit);

    // Tombol pemicu "Upload Jurnal Baru" di halaman manapun
    document
      .querySelectorAll("[data-ksm-open-upload]")
      .forEach((btn) =>
        btn.addEventListener("click", checkTokenAndOpenUpload),
      );

    resetForm();
  }

  document.addEventListener("DOMContentLoaded", init);
})(window);