import { CONFIG } from "../config.js";

export function showToast(message, type = "info") {
  const toast = document.createElement("div");
  toast.className = `toast-notification ${type}`;
  toast.innerHTML = message;

  Object.assign(toast.style, {
    position: "fixed",
    bottom: "30px",
    right: "30px",
    background:
      type === "success" ? "#10b981" : type === "error" ? "#ef4444" : "#3b82f6",
    color: "white",
    padding: "16px 24px",
    borderRadius: "12px",
    boxShadow: "0 10px 40px rgba(0,0,0,0.3)",
    zIndex: "10000",
    animation: "slideInUp 0.3s ease",
    maxWidth: "400px",
    fontSize: "15px",
    fontWeight: "500",
    lineHeight: "1.5",
  });

  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = "fadeOut 0.3s ease forwards";
    setTimeout(() => toast.remove(), 300);
  }, CONFIG.TOAST_DURATION);
}

// Inject animation styles
const styles = document.createElement("style");
styles.textContent = `
    @keyframes slideInUp {
        from { transform: translateY(100px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    @media (max-width: 768px) {
        .toast-notification {
            right: 15px !important;
            left: 15px !important;
            bottom: 20px !important;
            max-width: calc(100% - 30px) !important;
        }
    }
`;
document.head.appendChild(styles);
