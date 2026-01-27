// Import API functions
import { listJournals, listOpinions } from '../api.js';

// Import config
import { CONFIG } from '../config.js';

// Import transformers
import { transformItem } from '../utils/data-transformer.js';

// Import renderers
import { renderJournalCard, renderOpinionCard, renderEmptyState } from '../renderers/card-renderer.js';

/**
 * Pagination Manager - Support Journals & Opinions
 */
export class PaginationManager {
    constructor(options = {}) {
        this.containerSelector = options.containerSelector || "#journalContainer";
        this.paginationSelector = options.paginationSelector || "#pagination";
        this.searchInputSelector = options.searchInputSelector || "#searchInput";
        this.sortSelectSelector = options.sortSelectSelector || "#sortSelect";
        this.filterSelectSelector = options.filterSelectSelector || "#filterSelect";

        this.itemsPerPage = options.itemsPerPage || CONFIG.ITEMS_PER_PAGE || 9;
        this.currentPage = 1;
        this.dataType = options.dataType || "jurnal";

        this.allItems = [];
        this.filteredItems = [];
        this.currentSort = "newest";
        this.currentFilter = "all";

        console.log(`PaginationManager initializing for ${this.dataType}...`);
        this.init();
    }

    async init() {
        await this.loadData();
        this.setupSearch();
        this.setupSort();
        this.setupFilter();
        this.setupIconSort();
        this.applyFiltersAndSort();

        // Listen to changes
        window.addEventListener(`${this.dataType}s:changed`, async () => {
            console.log(`${this.dataType}s changed event received`);
            await this.loadData();
            this.applyFiltersAndSort();
        });

        console.log(`PaginationManager initialized with ${this.allItems.length} ${this.dataType}s`);
    }

    /**
     * Load data from API
     */
    async loadData() {
        try {
            console.log(`Loading ${this.dataType}s from database...`);

            // Use API functions instead of manual fetch
            const data = this.dataType === "jurnal" 
                ? await listJournals(100, 0) 
                : await listOpinions(100, 0);

            if (data.ok && data.results) {
                this.allItems = data.results.map(item => transformItem(item, this.dataType));
                this.filteredItems = [...this.allItems];
                console.log(`Loaded ${this.allItems.length} ${this.dataType}s from database`);
            } else {
                console.warn(`No ${this.dataType}s found in database`);
                this.allItems = [];
                this.filteredItems = [];
            }
        } catch (error) {
            console.error(`Error loading ${this.dataType}s:`, error);
            
            // Fallback to localStorage
            console.warn("Falling back to localStorage...");
            const storageKey = this.dataType === "jurnal" ? "journals" : "opinions";
            const stored = localStorage.getItem(storageKey);

            if (stored) {
                try {
                    this.allItems = JSON.parse(stored);
                    this.filteredItems = [...this.allItems];
                    console.log(`Loaded ${this.allItems.length} ${this.dataType}s from localStorage`);
                } catch (e) {
                    console.error("Failed to parse localStorage:", e);
                    this.allItems = [];
                    this.filteredItems = [];
                }
            } else {
                this.allItems = [];
                this.filteredItems = [];
            }
        }
    }

    /**
     * Render items
     */
    render() {
        const container = document.querySelector(this.containerSelector);
        if (!container) {
            console.warn("Container not found:", this.containerSelector);
            return;
        }

        container.innerHTML = "";
        this.updateTotalCount();

        // Empty state
        if (this.filteredItems.length === 0) {
            const emptyState = renderEmptyState(this.dataType);
            container.appendChild(emptyState);
            return;
        }

        // Paginate items
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const itemsToShow = this.filteredItems.slice(start, end);

        // Render cards
        const renderCard = this.dataType === "jurnal" ? renderJournalCard : renderOpinionCard;
        
        itemsToShow.forEach(item => {
            const card = renderCard(item, (item, url) => this.handleShare(item, url));
            container.appendChild(card);
        });

        this.renderPagination();

        // Replace feather icons
        if (typeof feather !== "undefined") {
            feather.replace();
        }
    }

    /**
     * Handle share functionality
     */
    handleShare(item, url) {
        const fullUrl = window.location.origin + '/' + url;
        const shareText = `Lihat ${this.dataType}: ${item.title}`;

        // Try native share API (mobile)
        if (navigator.share) {
            navigator.share({
                title: item.title,
                text: shareText,
                url: fullUrl
            })
            .then(() => console.log('Shared successfully'))
            .catch(error => {
                if (error.name !== 'AbortError') {
                    this.fallbackShare(fullUrl, shareText);
                }
            });
        } else {
            this.fallbackShare(fullUrl, shareText);
        }
    }

    fallbackShare(url, text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(() => alert('Link berhasil disalin ke clipboard!'))
                .catch(() => this.legacyCopy(url));
        } else {
            this.legacyCopy(url);
        }
    }

    legacyCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            alert('Link berhasil disalin!');
        } catch (err) {
            prompt('Copy link ini:', text);
        }
        
        document.body.removeChild(textarea);
    }

    /**
     * Render pagination controls
     */
    renderPagination() {
        const paginationContainer = document.querySelector(this.paginationSelector);
        if (!paginationContainer) return;

        const totalPages = Math.ceil(this.filteredItems.length / this.itemsPerPage);

        if (totalPages <= 1) {
            paginationContainer.innerHTML = "";
            return;
        }

        paginationContainer.innerHTML = "";

        // Previous button
        const prevBtn = document.createElement("button");
        prevBtn.textContent = "Previous";
        prevBtn.className = "pagination-btn";
        prevBtn.disabled = this.currentPage === 1;
        prevBtn.onclick = () => this.goToPage(this.currentPage - 1);
        paginationContainer.appendChild(prevBtn);

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        // First page + ellipsis
        if (startPage > 1) {
            const firstBtn = this.createPageButton(1);
            paginationContainer.appendChild(firstBtn);

            if (startPage > 2) {
                const ellipsis = document.createElement("span");
                ellipsis.textContent = "...";
                ellipsis.className = "pagination-ellipsis";
                paginationContainer.appendChild(ellipsis);
            }
        }

        // Page numbers
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = this.createPageButton(i);
            paginationContainer.appendChild(pageBtn);
        }

        // Last page + ellipsis
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const ellipsis = document.createElement("span");
                ellipsis.textContent = "...";
                ellipsis.className = "pagination-ellipsis";
                paginationContainer.appendChild(ellipsis);
            }

            const lastBtn = this.createPageButton(totalPages);
            paginationContainer.appendChild(lastBtn);
        }

        // Next button
        const nextBtn = document.createElement("button");
        nextBtn.textContent = "Next";
        nextBtn.className = "pagination-btn";
        nextBtn.disabled = this.currentPage === totalPages;
        nextBtn.onclick = () => this.goToPage(this.currentPage + 1);
        paginationContainer.appendChild(nextBtn);
    }

    createPageButton(pageNum) {
        const btn = document.createElement("button");
        btn.textContent = pageNum;
        btn.className = pageNum === this.currentPage ? "pagination-btn active" : "pagination-btn";
        btn.onclick = () => this.goToPage(pageNum);
        return btn;
    }

    goToPage(pageNum) {
        this.currentPage = pageNum;
        this.render();
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    updateTotalCount() {
        const possibleIds = ["totalJournals", "totalOpinions", "totalCount"];

        for (const id of possibleIds) {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = this.filteredItems.length;
                return;
            }
        }
    }

    setupSearch() {
        const searchInput = document.querySelector(this.searchInputSelector);
        if (!searchInput) return;

        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase().trim();
            this.applyFiltersAndSort(query);
        });
    }

    setupSort() {
        const sortSelect = document.querySelector(this.sortSelectSelector);
        if (sortSelect) {
            sortSelect.addEventListener("change", (e) => {
                this.currentSort = e.target.value;
                this.applyFiltersAndSort();
            });
        }

        window.addEventListener("sortChanged", (e) => {
            this.currentSort = e.detail.sortValue;
            this.applyFiltersAndSort();
        });
    }

    setupFilter() {
        const filterSelect = document.querySelector(this.filterSelectSelector);
        if (!filterSelect) return;

        filterSelect.addEventListener("change", (e) => {
            this.currentFilter = e.target.value;
            this.applyFiltersAndSort();
        });
    }

    setupIconSort() {
        const btnSort = document.getElementById("btnSort");
        const sortMenu = document.getElementById("sortMenu");
        const sortItems = document.querySelectorAll(".sort-item");
        const dropdown = document.querySelector(".sort-dropdown");

        if (!btnSort || !sortMenu) {
            console.log("Icon sort dropdown not found, skipping...");
            return;
        }

        btnSort.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdown.classList.toggle("active");
        });

        sortItems.forEach(item => {
            item.addEventListener("click", (e) => {
                const sortValue = e.currentTarget.dataset.sort;

                sortItems.forEach(i => i.classList.remove("active"));
                e.currentTarget.classList.add("active");

                dropdown.classList.remove("active");

                this.currentSort = sortValue;
                this.applyFiltersAndSort();
            });
        });

        document.addEventListener("click", (e) => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove("active");
            }
        });
    }

    applyFiltersAndSort(searchQuery = null) {
        let items = [...this.allItems];

        // Search
        const query = searchQuery !== null 
            ? searchQuery 
            : document.querySelector(this.searchInputSelector)?.value.toLowerCase().trim() || "";

        if (query) {
            items = items.filter(item => {
                if (this.dataType === "jurnal") {
                    return (
                        item.title.toLowerCase().includes(query) ||
                        item.abstract.toLowerCase().includes(query) ||
                        (Array.isArray(item.authors) && item.authors.some(a => a.toLowerCase().includes(query))) ||
                        (Array.isArray(item.tags) && item.tags.some(t => t.toLowerCase().includes(query)))
                    );
                } else {
                    return (
                        item.title.toLowerCase().includes(query) ||
                        item.description.toLowerCase().includes(query) ||
                        item.author_name.toLowerCase().includes(query) ||
                        item.category.toLowerCase().includes(query)
                    );
                }
            });
        }

        // Filter
        if (this.currentFilter !== "all") {
            items = items.filter(item => {
                if (this.dataType === "jurnal") {
                    return Array.isArray(item.tags) && item.tags.includes(this.currentFilter);
                } else {
                    return item.category === this.currentFilter;
                }
            });
        }

        // Sort
        if (this.currentSort === "newest") {
            items.sort((a, b) => new Date(b.uploadDate) - new Date(a.uploadDate));
        } else if (this.currentSort === "oldest") {
            items.sort((a, b) => new Date(a.uploadDate) - new Date(b.uploadDate));
        } else if (this.currentSort === "title") {
            items.sort((a, b) => a.title.localeCompare(b.title));
        } else if (this.currentSort === "views") {
            items.sort((a, b) => (b.views || 0) - (a.views || 0));
        }

        this.filteredItems = items;
        this.currentPage = 1;
        this.render();
    }
}

// Auto-initialize
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("journalContainer");

    if (container) {
        console.log("Initializing PaginationManager...");

        const isOpinionsPage = window.location.pathname.includes("opinions");
        const dataType = isOpinionsPage ? "opini" : "jurnal";

        window.paginationManager = new PaginationManager({
            containerSelector: "#journalContainer",
            paginationSelector: "#pagination",
            searchInputSelector: "#searchInput",
            sortSelectSelector: "#sortSelect",
            filterSelectSelector: "#filterSelect",
            itemsPerPage: CONFIG.ITEMS_PER_PAGE || 9,
            dataType: dataType
        });

        console.log(`PaginationManager initialized for ${dataType}`);
    }
});

console.log("pagination-manager.js loaded");
