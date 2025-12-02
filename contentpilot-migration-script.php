<?php
/**
 * ContentPilot Migration Script
 *
 * This script performs comprehensive rebranding from AI Auto News Poster (AANP)
 * to ContentPilot. It handles file renaming, class renaming, constant updates,
 * and all code references systematically.
 *
 * Usage: php contentpilot-migration-script.php
 */

class ContentPilotMigration {
    private $base_dir;
    private $dry_run = false;
    private $verbose = true;
    private $changes_made = 0;
    private $files_processed = 0;
    private $errors = [];

    public function __construct($base_dir = '.') {
        $this->base_dir = rtrim($base_dir, '/');
        $this->log("🚀 ContentPilot Migration Script Initialized");
        $this->log("Base Directory: " . $this->base_dir);
    }

    public function run() {
        $this->log("🔧 Starting ContentPilot Migration Process...");

        try {
            // Step 1: File and Directory Renaming
            $this->renameFilesAndDirectories();

            // Step 2: Class and Constant Renaming
            $this->renameClassesAndConstants();

            // Step 3: Code References Update
            $this->updateCodeReferences();

            // Step 4: Database Schema Updates
            $this->updateDatabaseReferences();

            // Step 5: User Interface Updates
            $this->updateUserInterfaceText();

            $this->log("✅ Migration completed successfully!");
            $this->log("Files processed: " . $this->files_processed);
            $this->log("Changes made: " . $this->changes_made);

            if (!empty($this->errors)) {
                $this->log("⚠️  Errors encountered: " . count($this->errors));
                foreach ($this->errors as $error) {
                    $this->log("  - " . $error);
                }
            }

        } catch (Exception $e) {
            $this->log("❌ Migration failed: " . $e->getMessage());
            return false;
        }

        return true;
    }

    private function renameFilesAndDirectories() {
        $this->log("📁 Step 1/5: Renaming files and directories...");

        // Rename directories
        $directories = [
            'ai-auto-news-poster-humanizer' => 'contentpilot-humanizer',
        ];

        foreach ($directories as $old => $new) {
            $old_path = $this->base_dir . '/' . $old;
            $new_path = $this->base_dir . '/' . $new;

            if (file_exists($old_path)) {
                if (!file_exists($new_path)) {
                    if ($this->dry_run) {
                        $this->log("  [DRY RUN] Would rename directory: $old_path → $new_path");
                    } else {
                        if (rename($old_path, $new_path)) {
                            $this->log("  ✅ Renamed directory: $old → $new");
                            $this->changes_made++;
                        } else {
                            $this->errors[] = "Failed to rename directory: $old_path";
                            $this->log("  ❌ Failed to rename directory: $old_path");
                        }
                    }
                } else {
                    $this->log("  ⚠️  Target directory already exists: $new_path");
                }
            }
        }

        // Rename files
        $files = [
            'ai-auto-news-poster' => 'contentpilot',
        ];

        foreach ($files as $old => $new) {
            $old_path = $this->base_dir . '/' . $old;
            $new_path = $this->base_dir . '/' . $new;

            if (file_exists($old_path)) {
                if (!file_exists($new_path)) {
                    if ($this->dry_run) {
                        $this->log("  [DRY RUN] Would rename file: $old_path → $new_path");
                    } else {
                        if (rename($old_path, $new_path)) {
                            $this->log("  ✅ Renamed file: $old → $new");
                            $this->changes_made++;
                        } else {
                            $this->errors[] = "Failed to rename file: $old_path";
                            $this->log("  ❌ Failed to rename file: $old_path");
                        }
                    }
                } else {
                    $this->log("  ⚠️  Target file already exists: $new_path");
                }
            }
        }
    }

    private function renameClassesAndConstants() {
        $this->log("🔧 Step 2/5: Renaming classes and constants...");

        // Define migration patterns
        $patterns = [
            // Class name patterns
            'AANP_' => 'CP_',
            'AI_Auto_News_Poster' => 'ContentPilot',
            'AANP_ServiceRegistry' => 'CP_ServiceRegistry',
            'AANP_ServiceOrchestrator' => 'CP_ServiceOrchestrator',
            'AANP_Error_Handler' => 'CP_Error_Handler',
            'AANP_Logger' => 'CP_Logger',
            'AANP_Cache_Manager' => 'CP_Cache_Manager',
            'AANP_AdvancedCacheManager' => 'CP_AdvancedCacheManager',
            'AANP_Security_Manager' => 'CP_Security_Manager',
            'AANP_Rate_Limiter' => 'CP_Rate_Limiter',
            'AANP_News_Fetch' => 'CP_News_Fetch',
            'AANP_AI_Generator' => 'CP_AI_Generator',
            'AANP_Post_Creator' => 'CP_Post_Creator',
            'AANP_ContentVerifier' => 'CP_ContentVerifier',
            'AANP_InstallationWizard' => 'CP_InstallationWizard',
            'AANP_HostingCompatibility' => 'CP_HostingCompatibility',
            'AANP_DependencyManager' => 'CP_DependencyManager',

            // Constant patterns
            'AANP_VERSION' => 'CP_VERSION',
            'AANP_PLUGIN_DIR' => 'CP_PLUGIN_DIR',
            'AANP_PLUGIN_URL' => 'CP_PLUGIN_URL',
            'AANP_PLUGIN_FILE' => 'CP_PLUGIN_FILE',
            'AANP_TESTING' => 'CP_TESTING',
            'AANP_PLUGIN_VERSION' => 'CP_PLUGIN_VERSION',

            // Database patterns
            'aanp_' => 'cp_',
            'wp_aanp_' => 'wp_cp_',
        ];

        $this->processFilesRecursively($this->base_dir, $patterns, ['.php', '.js', '.css', '.html']);
    }

    private function updateCodeReferences() {
        $this->log("🔗 Step 3/5: Updating code references...");

        // Define code reference patterns
        $patterns = [
            // Error messages and text
            'AI Auto News Poster' => 'ContentPilot',
            'ai-auto-news-poster' => 'contentpilot',
            'AANP' => 'CP',

            // Function calls and method references
            'AANP_Error_Handler::getInstance()' => 'CP_Error_Handler::getInstance()',
            'AANP_Logger::getInstance()' => 'CP_Logger::getInstance()',
            'AANP_Cache_Manager::getInstance()' => 'CP_Cache_Manager::getInstance()',

            // Service registry patterns
            'AANP_ServiceRegistry' => 'CP_ServiceRegistry',
            'AANP_ServiceOrchestrator' => 'CP_ServiceOrchestrator',

            // Database table references
            'aanp_generated_posts' => 'cp_generated_posts',
            'aanp_verified_sources' => 'cp_verified_sources',
        ];

        $this->processFilesRecursively($this->base_dir, $patterns, ['.php', '.js', '.css', '.html']);
    }

    private function updateDatabaseReferences() {
        $this->log("🗃️ Step 4/5: Updating database references...");

        // Database-specific patterns
        $patterns = [
            'CREATE TABLE .*aanp_' => 'CREATE TABLE ${1}cp_',
            'DROP TABLE .*aanp_' => 'DROP TABLE ${1}cp_',
            'ALTER TABLE .*aanp_' => 'ALTER TABLE ${1}cp_',
            'FROM .*aanp_' => 'FROM ${1}cp_',
            'JOIN .*aanp_' => 'JOIN ${1}cp_',
            'wp_aanp_' => 'wp_cp_',
        ];

        $this->processFilesRecursively($this->base_dir, $patterns, ['.php', '.sql']);
    }

    private function updateUserInterfaceText() {
        $this->log("🎨 Step 5/5: Updating user interface text...");

        // UI-specific patterns
        $patterns = [
            'Generated by AI Auto News Poster' => 'Generated by ContentPilot',
            'Content verified by AI Auto News Poster' => 'Content verified by ContentPilot',
            'AI Auto News Poster Error' => 'ContentPilot Error',
            'AI Auto News Poster - ' => 'ContentPilot - ',
            'AI Auto News Poster:' => 'ContentPilot:',
            'AI Auto News Poster plugin' => 'ContentPilot plugin',
            'AI Auto News Poster settings' => 'ContentPilot settings',
            'AI Auto News Poster dashboard' => 'ContentPilot dashboard',
        ];

        $this->processFilesRecursively($this->base_dir, $patterns, ['.php', '.js', '.css', '.html', '.txt']);
    }

    private function processFilesRecursively($directory, $patterns, $extensions) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && in_array($item->getExtension(), $extensions)) {
                $file_path = $item->getPathname();
                $this->files_processed++;

                try {
                    $content = file_get_contents($file_path);
                    $original_content = $content;
                    $changes_in_file = 0;

                    foreach ($patterns as $search => $replace) {
                        // Use regex for more complex patterns
                        if (strpos($search, '${') !== false) {
                            // Handle regex patterns
                            $regex = str_replace('\${', '(?<replace>', $search);
                            $regex = str_replace('}', '>', $regex);
                            $content = preg_replace("/$regex/", $replace, $content, -1, $count);
                        } else {
                            // Simple string replacement
                            $content = str_replace($search, $replace, $content, $count);
                        }

                        if ($count > 0) {
                            $changes_in_file += $count;
                            $this->changes_made += $count;
                        }
                    }

                    if ($changes_in_file > 0) {
                        if ($this->dry_run) {
                            $this->log("  [DRY RUN] Would update " . basename($file_path) . " ($changes_in_file changes)");
                        } else {
                            file_put_contents($file_path, $content);
                            $this->log("  ✅ Updated " . basename($file_path) . " ($changes_in_file changes)");
                        }
                    }

                } catch (Exception $e) {
                    $this->errors[] = "Error processing file $file_path: " . $e->getMessage();
                    $this->log("  ❌ Error processing file: " . basename($file_path) . " - " . $e->getMessage());
                }
            }
        }
    }

    private function log($message) {
        if ($this->verbose) {
            echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        }
    }

    public function setDryRun($dry_run = true) {
        $this->dry_run = $dry_run;
        $this->log("🔧 Dry run mode: " . ($dry_run ? "ENABLED" : "DISABLED"));
    }

    public function getFilesProcessed() {
        return $this->files_processed;
    }

    public function getChangesMade() {
        return $this->changes_made;
    }

    public function getErrors() {
        return $this->errors;
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "🚀 ContentPilot Migration Script\n";
    echo "================================\n\n";

    $migration = new ContentPilotMigration();

    // Check for dry-run flag
    if (isset($argv[1]) && $argv[1] === '--dry-run') {
        $migration->setDryRun(true);
    }

    $start_time = microtime(true);
    $success = $migration->run();
    $end_time = microtime(true);

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 Migration Summary\n";
    echo str_repeat("=", 50) . "\n";
    echo "Status: " . ($success ? "✅ SUCCESS" : "❌ FAILED") . "\n";
    echo "Execution Time: " . round($end_time - $start_time, 2) . " seconds\n";
    echo "Files Processed: " . $migration->getFilesProcessed() . "\n";
    echo "Changes Made: " . $migration->getChangesMade() . "\n";
    echo "Errors: " . count($migration->getErrors()) . "\n";

    if (!$success) {
        exit(1);
    }
} else {
    echo "This script must be run from the command line.";
}