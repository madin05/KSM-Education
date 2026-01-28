/**
 * Parse JSON field (handle string, array, or null)
 */
export function parseJsonField(field) {
  if (!field) return [];
  if (Array.isArray(field)) return field;
  try {
    return JSON.parse(field);
  } catch (e) {
    return [];
  }
}

/**
 * Transform journal item from database format
 */
export function transformJournal(item) {
  return {
    id: String(item.id),
    title: item.title || "Untitled",
    abstract: item.abstract || "",
    authors: parseJsonField(item.authors),
    tags: parseJsonField(item.tags),
    pengurus: parseJsonField(item.pengurus),
    volume: item.volume || "",
    date: item.created_at,
    uploadDate: item.created_at,
    fileData: item.file_url,
    file: item.file_url,
    coverImage:
      item.cover_url ||
      "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
    email: item.email || "",
    contact: item.contact || "",
    phone: item.contact || "",
    views: parseInt(item.views) || 0,
  };
}

/**
 * Transform opinion item from database format
 */
export function transformOpinion(item) {
  return {
    id: String(item.id),
    title: item.title || "Untitled",
    description: item.description || "",
    category: item.category || "opini",
    author_name: item.author_name || "Anonymous",
    tags: parseJsonField(item.tags), // Use shared helper
    date: item.created_at,
    uploadDate: item.created_at,
    coverImage:
      item.cover_url ||
      "https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop",
    fileUrl: item.file_url,
    file: item.file_url,
    views: parseInt(item.views) || 0,
  };
}

/**
 * Transform item based on type
 */
export function transformItem(item, dataType) {
  return dataType === "jurnal" || dataType === "journal"
    ? transformJournal(item)
    : transformOpinion(item);
}

console.log("data-transformer.js loaded");
