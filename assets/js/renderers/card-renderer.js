/**
 * Truncate text to max length
 */
function truncate(text, max) {
    if (!text) return "";
    return text.length > max ? text.substring(0, max) + "..." : text;
}

/**
 * Format date to Indonesian locale
 */
function formatDate(dateString) {
    try {
        return new Date(dateString).toLocaleDateString("id-ID", {
            day: "numeric",
            month: "short",
            year: "numeric"
        });
    } catch (e) {
        return dateString;
    }
}

/**
 * Render journal card
 */
export function renderJournalCard(item, onShare) {
    const card = document.createElement("div");
    card.className = "journal-card";
    card.setAttribute("data-jurnal-id", item.id);

    const author = Array.isArray(item.authors) && item.authors.length > 0 
        ? item.authors[0] 
        : "Unknown";

    const exploreUrl = `explore_jurnal_user.html?id=${item.id}&type=jurnal`;

    card.innerHTML = `
        <div class="journal-cover" data-explore-url="${exploreUrl}">
            <img src="${item.coverImage}" alt="${item.title}" 
                 onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
            <div class="journal-views">
                <i data-feather="eye"></i> ${item.views}
            </div>
        </div>
        <div class="journal-content">
            <h3 class="journal-title">${truncate(item.title, 60)}</h3>
            <p class="journal-abstract">${truncate(item.abstract, 150)}</p>
            <div class="journal-meta">
                <span class="journal-author"><i data-feather="user"></i> ${author}</span>
                <span class="journal-date"><i data-feather="calendar"></i> ${formatDate(item.uploadDate)}</span>
            </div>
            ${item.tags && item.tags.length > 0 ? `
                <div class="journal-tags">
                    ${item.tags.slice(0, 3).map(tag => `<span class="tag">${tag}</span>`).join("")}
                    ${item.tags.length > 3 ? `<span class="tag-more">+${item.tags.length - 3}</span>` : ""}
                </div>
            ` : ""}
            <div class="journal-actions">
                <button class="btn-share" data-journal-id="${item.id}" 
                        data-journal-title="${item.title}"
                        data-journal-url="${exploreUrl}">
                    <i data-feather="share-2"></i> Share
                </button>
            </div>
        </div>
    `;

    // Add cover click handler
    const coverDiv = card.querySelector('.journal-cover');
    if (coverDiv) {
        coverDiv.addEventListener('click', () => {
            window.location.href = exploreUrl;
        });
    }

    // Add share handler
    const shareBtn = card.querySelector('.btn-share');
    if (shareBtn) {
        shareBtn.addEventListener('click', (e) => {
            e.preventDefault();
            onShare(item, exploreUrl);
        });
    }

    return card;
}

/**
 * Render opinion card
 */
export function renderOpinionCard(item, onShare) {
    const card = document.createElement("div");
    card.className = "opinion-card";
    card.setAttribute("data-opini-id", item.id);

    const exploreUrl = `explore_opini_user.html?id=${item.id}&type=opini`;

    card.innerHTML = `
        <div class="opinion-cover" data-explore-url="${exploreUrl}">
            <img src="${item.coverImage}" alt="${item.title}"
                 onerror="this.src='https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop'">
            <div class="opinion-views">
                <i data-feather="eye"></i> ${item.views}
            </div>
        </div>
        <div class="opinion-content">
            <span class="opinion-category">${item.category}</span>
            <h3 class="opinion-title">${truncate(item.title, 60)}</h3>
            <p class="opinion-description">${truncate(item.description, 150)}</p>
            <div class="opinion-meta">
                <span class="opinion-author"><i data-feather="user"></i> ${item.author_name}</span>
                <span class="opinion-date"><i data-feather="calendar"></i> ${formatDate(item.uploadDate)}</span>
            </div>
            <div class="opinion-tags">
                ${item.tags.slice(0, 3).map(tag => `<span class="tag">${tag}</span>`).join("")}
                ${item.tags.length > 3 ? `<span class="tag-more">+${item.tags.length - 3}</span>` : ""}
            </div>
            <div class="opinion-actions">
                <button class="btn-share" data-opinion-id="${item.id}" 
                        data-opinion-title="${item.title}"
                        data-opinion-url="${exploreUrl}">
                    <i data-feather="share-2"></i> Share
                </button>
            </div>
        </div>
    `;

    // Add cover click handler
    const coverDiv = card.querySelector('.opinion-cover');
    if (coverDiv) {
        coverDiv.addEventListener('click', () => {
            window.location.href = exploreUrl;
        });
    }

    // Add share handler
    const shareBtn = card.querySelector('.btn-share');
    if (shareBtn) {
        shareBtn.addEventListener('click', (e) => {
            e.preventDefault();
            onShare(item, exploreUrl);
        });
    }

    return card;
}

/**
 * Render empty state
 */
export function renderEmptyState(dataType) {
    const div = document.createElement("div");
    div.className = "empty-state";
    div.innerHTML = `
        <div class="empty-state-icon">${dataType === "jurnal" ? "📚" : "📝"}</div>
        <h3>Tidak Ada ${dataType === "jurnal" ? "Jurnal" : "Opini"}</h3>
        <p>Belum ada ${dataType} yang tersedia</p>
    `;
    return div;
}
