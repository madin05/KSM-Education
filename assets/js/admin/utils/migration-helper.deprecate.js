// Import API functions
import { createJournal, createOpinion } from "../api.js";

/**
 * Migration Helper - Migrate localStorage data to database
 * Usage: await MigrationHelper.migrateAll()
 */
export class MigrationHelper {
  /**
   * Migrate all data (journals + opinions)
   */
  static async migrateAll() {
    console.log("Starting automatic migration...");

    const results = {
      journals: { success: 0, failed: 0, skipped: 0 },
      opinions: { success: 0, failed: 0, skipped: 0 },
      total: 0,
    };

    // Migrate journals
    const journalResults = await this.migrateJournals();
    results.journals = journalResults;

    // Migrate opinions
    const opinionResults = await this.migrateOpinions();
    results.opinions = opinionResults;

    // Calculate total
    results.total = results.journals.success + results.opinions.success;

    // Print summary
    this.printSummary(results);

    return results;
  }

  /**
   * Migrate journals from localStorage to database
   */
  static async migrateJournals() {
    const results = { success: 0, failed: 0, skipped: 0 };

    try {
      const journalsRaw = localStorage.getItem("journals");
      if (!journalsRaw) {
        console.log("No journals found in localStorage");
        return results;
      }

      const journals = JSON.parse(journalsRaw);
      console.log(`Found ${journals.length} journals to migrate`);

      for (const journal of journals) {
        // Skip already migrated
        if (journal.server_id || journal.migrated_at) {
          results.skipped++;
          console.log(`Skipped (already migrated): ${journal.title}`);
          continue;
        }

        try {
          // Transform old format to new format
          const metadata = this.transformJournalForMigration(journal);

          // Create via API
          const result = await createJournal(metadata);

          if (result.ok) {
            // Mark as migrated
            journal.server_id = result.id;
            journal.migrated_at = new Date().toISOString();
            results.success++;
            console.log(`Migrated journal: ${journal.title}`);
          } else {
            results.failed++;
            console.error(`Failed journal: ${journal.title}`, result.message);
          }
        } catch (err) {
          results.failed++;
          console.error(`❌ Error migrating journal: ${journal.title}`, err);
        }

        // Delay to prevent rate limiting
        await this.delay(100);
      }

      // Update localStorage with server IDs
      localStorage.setItem("journals", JSON.stringify(journals));
      console.log("Updated localStorage with server IDs");
    } catch (err) {
      console.error("Journal migration error:", err);
    }

    return results;
  }

  /**
   * Migrate opinions from localStorage to database
   */
  static async migrateOpinions() {
    const results = { success: 0, failed: 0, skipped: 0 };

    try {
      const opinionsRaw = localStorage.getItem("opinions");
      if (!opinionsRaw) {
        console.log("No opinions found in localStorage");
        return results;
      }

      const opinions = JSON.parse(opinionsRaw);
      console.log(`Found ${opinions.length} opinions to migrate`);

      for (const opinion of opinions) {
        // Skip already migrated
        if (opinion.server_id || opinion.migrated_at) {
          results.skipped++;
          console.log(`Skipped (already migrated): ${opinion.title}`);
          continue;
        }

        try {
          // Transform old format to new format
          const metadata = this.transformOpinionForMigration(opinion);

          // Create via API
          const result = await createOpinion(metadata);

          if (result.ok) {
            // Mark as migrated
            opinion.server_id = result.id;
            opinion.migrated_at = new Date().toISOString();
            results.success++;
            console.log(`Migrated opinion: ${opinion.title}`);
          } else {
            results.failed++;
            console.error(`Failed opinion: ${opinion.title}`, result.message);
          }
        } catch (err) {
          results.failed++;
          console.error(`Error migrating opinion: ${opinion.title}`, err);
        }

        // Delay to prevent rate limiting
        await this.delay(100);
      }

      // Update localStorage with server IDs
      localStorage.setItem("opinions", JSON.stringify(opinions));
      console.log("Updated localStorage with server IDs");
    } catch (err) {
      console.error("Opinion migration error:", err);
    }

    return results;
  }

  /**
   * Transform journal from old format to API format
   */
  static transformJournalForMigration(journal) {
    return {
      title: journal.title || "Untitled",
      abstract: journal.abstract || "",
      authors: journal.author || journal.authors || [], // Handle both old & new field names
      tags: journal.tags || [],
      pengurus: journal.pengurus || [],
      volume: journal.volume || "",
      fileUrl: journal.fileData || journal.file || "",
      coverUrl: journal.coverImage || "",
      email: journal.email || "",
      contact: journal.contact || journal.phone || "",
      client_temp_id: journal.id || "migration_" + Date.now(),
      client_updated_at:
        journal.date || journal.uploadDate || new Date().toISOString(),
    };
  }

  /**
   * Transform opinion from old format to API format
   */
  static transformOpinionForMigration(opinion) {
    return {
      title: opinion.title || "Untitled",
      description: opinion.description || "",
      category: opinion.category || "opini",
      author_name: opinion.author || opinion.author_name || "Anonymous",
      tags: JSON.stringify(opinion.tags || []),
      fileUrl: opinion.fileUrl || opinion.file || "",
      coverUrl: opinion.coverImage || "",
      email: opinion.email || "",
      contact: opinion.contact || "",
    };
  }

  /**
   * Print migration summary
   */
  static printSummary(results) {
    console.log("\n╔════════════════════════════════════╗");
    console.log("║     MIGRATION SUMMARY              ║");
    console.log("╠════════════════════════════════════╣");
    console.log(`║ Journals:                          ║`);
    console.log(
      `║   Success: ${results.journals.success.toString().padEnd(19)} ║`,
    );
    console.log(
      `║   Failed:  ${results.journals.failed.toString().padEnd(19)} ║`,
    );
    console.log(
      `║   Skipped: ${results.journals.skipped.toString().padEnd(19)} ║`,
    );
    console.log("╠════════════════════════════════════╣");
    console.log(`║ Opinions:                          ║`);
    console.log(
      `║ Success: ${results.opinions.success.toString().padEnd(19)} ║`,
    );
    console.log(
      `║ Failed:  ${results.opinions.failed.toString().padEnd(19)} ║`,
    );
    console.log(
      `║ Skipped: ${results.opinions.skipped.toString().padEnd(19)} ║`,
    );
    console.log("╠════════════════════════════════════╣");
    console.log(`║ Total Migrated: ${results.total.toString().padEnd(18)} ║`);
    console.log("╚════════════════════════════════════╝\n");

    if (results.total > 0) {
      console.log("Migration completed successfully!");
      console.log("You can now safely clear localStorage data.");
    } else {
      console.log("No new data to migrate.");
    }
  }

  /**
   * Delay helper (prevent rate limiting)
   */
  static delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Clear migrated data from localStorage
   * (Only use after successful migration)
   */
  static clearMigratedData() {
    const confirmed = confirm(
      "WARNING: This will clear all localStorage data!\n\n" +
        "Make sure migration was successful before proceeding.\n\n" +
        "Continue?",
    );

    if (confirmed) {
      localStorage.removeItem("journals");
      localStorage.removeItem("opinions");
      console.log("localStorage cleared!");
    }
  }

  /**
   * Check migration status
   */
  static checkStatus() {
    const journalsRaw = localStorage.getItem("journals");
    const opinionsRaw = localStorage.getItem("opinions");

    const journals = journalsRaw ? JSON.parse(journalsRaw) : [];
    const opinions = opinionsRaw ? JSON.parse(opinionsRaw) : [];

    const journalsMigrated = journals.filter((j) => j.server_id).length;
    const opinionsMigrated = opinions.filter((o) => o.server_id).length;

    console.log("\n╔════════════════════════════════════╗");
    console.log("║     MIGRATION STATUS               ║");
    console.log("╠════════════════════════════════════╣");
    console.log(`║ Journals:                          ║`);
    console.log(`║   Total:    ${journals.length.toString().padEnd(22)} ║`);
    console.log(`║   Migrated: ${journalsMigrated.toString().padEnd(22)} ║`);
    console.log(
      `║   Pending:  ${(journals.length - journalsMigrated).toString().padEnd(22)} ║`,
    );
    console.log("╠════════════════════════════════════╣");
    console.log(`║ Opinions:                          ║`);
    console.log(`║   Total:    ${opinions.length.toString().padEnd(22)} ║`);
    console.log(`║   Migrated: ${opinionsMigrated.toString().padEnd(22)} ║`);
    console.log(
      `║   Pending:  ${(opinions.length - opinionsMigrated).toString().padEnd(22)} ║`,
    );
    console.log("╚════════════════════════════════════╝\n");

    return {
      journals: { total: journals.length, migrated: journalsMigrated },
      opinions: { total: opinions.length, migrated: opinionsMigrated },
    };
  }
}

// Expose to window for manual testing
if (typeof window !== "undefined") {
  window.MigrationHelper = MigrationHelper;
}

console.log("migration-helper.js loaded");
