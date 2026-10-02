<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    Download,
    FileSpreadsheet,
    FileText,
    ShieldAlert,
    Sparkles,
    Upload,
} from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    type DecryptedPayload,
    useVault,
    type VaultItemData,
} from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
    items: VaultItemData[];
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'imported'): void;
}>();

const { currentTeamSlug, encryptItemPayload } = useVault();

const mode = ref<'export' | 'import'>('export');
const exportFormat = ref<'json' | 'csv'>('csv');
const isProcessing = ref(false);

// Export Handler
function handleExport() {
    if (props.items.length === 0) {
        toast.error('No vault items available to export.');
        return;
    }

    try {
        let content = '';
        let mimeType = 'text/plain';
        let filename = `vault-export-${new Date().toISOString().slice(0, 10)}`;

        if (exportFormat.value === 'json') {
            const exportData = props.items.map((it) => ({
                type: it.type,
                title: it.decrypted?.title || it.title,
                username: it.decrypted?.username || '',
                password: it.decrypted?.password || '',
                url: it.decrypted?.url || '',
                notes: it.decrypted?.notes || '',
                totp: it.decrypted?.totp || '',
                folder: it.folder || '',
                is_favorite: it.is_favorite,
            }));
            content = JSON.stringify(exportData, null, 2);
            mimeType = 'application/json';
            filename += '.json';
        } else {
            // Standard CSV format (compatible with Bitwarden & 1Password)
            const headers = [
                'folder',
                'favorite',
                'type',
                'name',
                'notes',
                'fields',
                'reprompt',
                'login_uri',
                'login_username',
                'login_password',
                'login_totp',
            ];
            const rows = props.items.map((it) => {
                const dec = it.decrypted || { title: it.title };
                return [
                    escapeCsv(it.folder || ''),
                    it.is_favorite ? '1' : '0',
                    escapeCsv(it.type),
                    escapeCsv(dec.title || it.title),
                    escapeCsv(dec.notes || ''),
                    '', // extra fields
                    '0',
                    escapeCsv(dec.url || ''),
                    escapeCsv(dec.username || ''),
                    escapeCsv(dec.password || ''),
                    escapeCsv(dec.totp || ''),
                ].join(',');
            });
            content = [headers.join(','), ...rows].join('\n');
            mimeType = 'text/csv';
            filename += '.csv';
        }

        const blob = new Blob([content], {
            type: `${mimeType};charset=utf-8;`,
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        toast.success(`Exported ${props.items.length} items to ${filename}`);
        emit('update:open', false);
    } catch (e: any) {
        toast.error('Failed to export vault items.');
    }
}

function escapeCsv(str: string): string {
    if (!str) return '""';
    const escaped = str.replace(/"/g, '""');
    return `"${escaped}"`;
}

// Import Handler
const importFile = ref<File | null>(null);
const importPreviewCount = ref(0);

function onFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        importFile.value = target.files[0];
    }
}

async function handleImport() {
    if (!importFile.value) {
        toast.error('Please select a file to import.');
        return;
    }

    isProcessing.value = true;
    try {
        const text = await importFile.value.text();
        let parsedItems: DecryptedPayload[] = [];

        if (importFile.value.name.endsWith('.json')) {
            const raw = JSON.parse(text);
            const array = Array.isArray(raw) ? raw : raw.items || [];
            parsedItems = array.map((it: any) => ({
                title: it.title || it.name || 'Imported Item',
                username: it.username || it.login_username || '',
                password: it.password || it.login_password || '',
                url: it.url || it.login_uri || '',
                notes: it.notes || '',
                totp: it.totp || it.login_totp || '',
            }));
        } else {
            // Parse CSV lines
            const lines = text
                .split(/\r?\n/)
                .filter((l) => l.trim().length > 0);
            if (lines.length > 1) {
                // Skip header line
                for (let i = 1; i < lines.length; i++) {
                    const cols = parseCsvLine(lines[i]);
                    // Support Bitwarden CSV layout: folder(0), fav(1), type(2), name(3), notes(4), fields(5), reprompt(6), url(7), user(8), pwd(9), totp(10)
                    // Or Chrome CSV layout: name(0), url(1), username(2), password(3), note(4)
                    if (cols.length >= 10) {
                        parsedItems.push({
                            title: cols[3] || 'Imported Item',
                            notes: cols[4] || '',
                            url: cols[7] || '',
                            username: cols[8] || '',
                            password: cols[9] || '',
                            totp: cols[10] || '',
                        });
                    } else if (cols.length >= 4) {
                        parsedItems.push({
                            title: cols[0] || 'Imported Item',
                            url: cols[1] || '',
                            username: cols[2] || '',
                            password: cols[3] || '',
                            notes: cols[4] || '',
                        });
                    }
                }
            }
        }

        if (parsedItems.length === 0) {
            toast.error('No valid credentials found in file.');
            return;
        }

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';
        let successCount = 0;

        // Batch encrypt and send
        for (const item of parsedItems) {
            const { ciphertext, iv } = await encryptItemPayload(item);
            const res = await fetch(`/${currentTeamSlug.value}/vault/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    type: 'login',
                    title: item.title,
                    encrypted_data: ciphertext,
                    iv,
                    is_favorite: false,
                }),
            });
            if (res.ok) successCount++;
        }

        toast.success(
            `Successfully imported and encrypted ${successCount} credentials!`,
        );
        emit('imported');
        emit('update:open', false);
    } catch (e: any) {
        toast.error(e.message || 'Failed to parse and import file.');
    } finally {
        isProcessing.value = false;
    }
}

function parseCsvLine(text: string): string[] {
    const result: string[] = [];
    let cur = '';
    let inQuote = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (c === '"') {
            if (inQuote && text[i + 1] === '"') {
                cur += '"';
                i++;
            } else {
                inQuote = !inQuote;
            }
        } else if (c === ',' && !inQuote) {
            result.push(cur.trim());
            cur = '';
        } else {
            cur += c;
        }
    }
    result.push(cur.trim());
    return result;
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div
                    class="mb-2 grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm font-medium"
                >
                    <button
                        type="button"
                        class="rounded-md py-1.5 transition-colors"
                        :class="
                            mode === 'export'
                                ? 'bg-background font-semibold text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mode = 'export'"
                    >
                        Export Vault
                    </button>
                    <button
                        type="button"
                        class="rounded-md py-1.5 transition-colors"
                        :class="
                            mode === 'import'
                                ? 'bg-background font-semibold text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mode = 'import'"
                    >
                        Import Vault
                    </button>
                </div>
                <DialogTitle class="text-xl font-bold">
                    {{
                        mode === 'export'
                            ? 'Export Vault Data'
                            : 'Import Credentials'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        mode === 'export'
                            ? 'Download a decrypted backup of your items for storage or transfer.'
                            : 'Upload a CSV or JSON file from 1Password, Bitwarden, or Chrome.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- EXPORT TAB -->
            <div v-if="mode === 'export'" class="space-y-4 py-2">
                <div
                    class="flex items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 p-3.5 text-xs text-amber-600 dark:text-amber-400"
                >
                    <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <strong class="block font-semibold"
                            >Security Warning</strong
                        >
                        Exported files contain plaintext passwords. Store them
                        only on encrypted drives and delete them when no longer
                        needed.
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Export Format</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="flex flex-col items-center justify-center gap-1.5 rounded-xl border p-4 text-center transition-all"
                            :class="
                                exportFormat === 'csv'
                                    ? 'border-primary bg-primary/10 font-bold text-primary shadow-xs'
                                    : 'border-input hover:bg-muted/40'
                            "
                            @click="exportFormat = 'csv'"
                        >
                            <FileSpreadsheet class="h-6 w-6" />
                            <span class="text-xs">CSV (Universal)</span>
                        </button>
                        <button
                            type="button"
                            class="flex flex-col items-center justify-center gap-1.5 rounded-xl border p-4 text-center transition-all"
                            :class="
                                exportFormat === 'json'
                                    ? 'border-primary bg-primary/10 font-bold text-primary shadow-xs'
                                    : 'border-input hover:bg-muted/40'
                            "
                            @click="exportFormat = 'json'"
                        >
                            <FileText class="h-6 w-6" />
                            <span class="text-xs">JSON Backup</span>
                        </button>
                    </div>
                </div>

                <p class="text-center text-xs text-muted-foreground">
                    {{ items.length }} items will be included in the export.
                </p>

                <DialogFooter class="pt-2">
                    <Button
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        class="w-full gap-2 sm:w-auto"
                        @click="handleExport"
                    >
                        <Download class="h-4 w-4" />
                        Download File
                    </Button>
                </DialogFooter>
            </div>

            <!-- IMPORT TAB -->
            <div v-else class="space-y-4 py-2">
                <div
                    class="space-y-3 rounded-xl border border-dashed border-border p-6 text-center transition-colors hover:border-primary/60"
                >
                    <Upload class="mx-auto h-8 w-8 text-muted-foreground" />
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-foreground">
                            Click to select or drop CSV/JSON file
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Supported: Bitwarden, 1Password, Chrome, JSON
                        </p>
                    </div>
                    <input
                        type="file"
                        accept=".csv,.json"
                        class="hidden"
                        id="vault-import-input"
                        @change="onFileSelect"
                    />
                    <label
                        for="vault-import-input"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-input bg-background px-3 py-1.5 text-xs font-medium shadow-xs hover:bg-muted"
                    >
                        {{ importFile ? importFile.name : 'Choose File' }}
                    </label>
                </div>

                <div
                    v-if="importFile"
                    class="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-600"
                >
                    <Check class="h-4 w-4 shrink-0" />
                    <span
                        >Selected: <strong>{{ importFile.name }}</strong> ({{
                            (importFile.size / 1024).toFixed(1)
                        }}
                        KB)</span
                    >
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        class="w-full gap-2 sm:w-auto"
                        :disabled="!importFile || isProcessing"
                        @click="handleImport"
                    >
                        <Spinner v-if="isProcessing" />
                        <Upload v-else class="h-4 w-4" />
                        Encrypt & Import Items
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
