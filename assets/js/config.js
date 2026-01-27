export const CONFIG = {
  API_BASE: "/ksmaja/api",
  ITEMS_PER_PAGE: 50,
  MAX_DASHBOARD_ARTICLES: 6,
  TOAST_DURATION: 3000,

  // Upload configuration
  MAX_FILE_SIZE: 10 * 1024 * 1024, // 10MB untuk PDF
  MAX_COVER_SIZE: 2 * 1024 * 1024, // 2MB untuk cover
  ALLOWED_FILE_TYPES: ["application/pdf"],
  ALLOWED_IMAGE_TYPES: ["image/jpeg", "image/png", "image/gif"],

  STORAGE_KEYS: {
    VIEWED_ARTICLES: "viewedArticles",
    USER_EMAIL: "userEmail",
    USER_LOGGED_IN: "userLoggedIn",
  },
};
