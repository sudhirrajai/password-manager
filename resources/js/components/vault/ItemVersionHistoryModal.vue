<script setup lang="ts">
import {
    AlertCircle,
    Check,
    Clock,
    Copy,
    Download,
    Eye,
    EyeOff,
    FileKey,
    History,
    Key,
    RotateCcw,
    Server,
    Shield,
    Terminal,
    User,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    type DecryptedPayload,
    useVault,
    type VaultItemData,
} from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
    item: VaultItemData;
    currentItemDecrypted?: DecryptedPayload;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'restored', updatedItem: VaultItemData): void;
}>();

const { currentTeamSlug, decryptHistoricalPayload, copyToClipboardWithAutoClear } = useVault();

interface DecryptedHistoryEntry {
    id: number;
    user_id: number | null;
    user?: { id: number; name: string; email: string } | null;
    created_at: string;
    decrypted: DecryptedPayload;
    showPassword?: boolean;
    showSshPassword?: boolean;
    showPrivateKey?: boolean;
    isRestoring?: boolean;
}

const histories = ref<DecryptedHistoryEntry[]>([]);
const isLoading = ref(false);
const fetchError = ref<string | null>(null);

async function loadHistory() {
    if (!props.item?.id || !currentTeamSlug.value) return;

    isLoading.value = true;
    fetchError.value = null;
    histories.value = [];

    try {
        const res = await fetch(
            `/${currentTeamSlug.value}/vault/items/${props.item.id}/history`,
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        if (!res.ok) {
            throw new Error(`Failed to load history (${res.status})`);
        }

        const data = await res.json();
        const rawList = data.history || [];

        const decryptedList: DecryptedHistoryEntry[] = [];
        for (const raw of rawList) {
            const dec = await decryptHistoricalPayload(raw.encrypted_data, raw.iv);
            decryptedList.push({
                id: raw.id,
                user_id: raw.user_id,
                user: raw.user,
                created_at: raw.created_at,
                decrypted: dec,
                showPassword: false,
                showSshPassword: false,
                showPrivateKey: false,
                isRestoring: false,
            });
        }

        histories.value = decryptedList;
    } catch (e: any) {
        console.error('Error loading item history:', e);
        fetchError.value = e.message || 'Unable to retrieve version history.';
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            loadHistory();
        }
    },
);

function formatDate(iso: string): string {
    try {
        const d = new Date(iso);
        return d.toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return iso;
    }
}

function downloadKeyFile(entry: DecryptedHistoryEntry) {
    if (!entry.decrypted.privateKey) return;
    const filename =
        entry.decrypted.privateKeyFileName ||
        `${props.item.title.toLowerCase().replace(/[^a-z0-9_-]/g, '_') || 'server'}_backup.pem`;

    const blob = new Blob([entry.decrypted.privateKey], {
        type: 'application/x-pem-file',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    toast.success(`Downloaded historical key file: ${filename}`);
}

async function restoreVersion(entry: DecryptedHistoryEntry) {
    if (!confirm(`Are you sure you want to restore the version from ${formatDate(entry.created_at)}? The current version will be archived first.`)) {
        return;
    }

    entry.isRestoring = true;
    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(
            `/${currentTeamSlug.value}/vault/items/${props.item.id}/history/${entry.id}/restore`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.message || 'Failed to restore historical version.');
        }

        const data = await res.json();
        const updatedItem: VaultItemData = {
            ...data.item,
            decrypted: entry.decrypted,
        };

        toast.success(`Restored version from ${formatDate(entry.created_at)}!`);
        emit('restored', updatedItem);
        emit('update:open', false);
    } catch (e: any) {
        toast.error(e.message || 'Error restoring version.');
    } finally {
        entry.isRestoring = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <History class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold">Credential Version History</DialogTitle>
                        <DialogDescription class="text-xs">
                            Audit all past changes, inspect previous passwords, and recover previous .pem / .ppk keys.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Current Version Quick Summary -->
            <div class="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-foreground">Current Active Version</span>
                    <Badge variant="outline" class="bg-primary/10 text-[11px] font-medium text-primary">
                        Active Now
                    </Badge>
                </div>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 text-muted-foreground">
                    <div v-if="currentItemDecrypted?.username">
                        <span class="text-[11px] text-muted-foreground/80">Username: </span>
                        <span class="font-mono text-foreground">{{ currentItemDecrypted.username }}</span>
                    </div>
                    <div v-if="currentItemDecrypted?.password">
                        <span class="text-[11px] text-muted-foreground/80">Current Password: </span>
                        <span class="font-mono text-foreground">••••••••••••</span>
                    </div>
                    <div v-if="currentItemDecrypted?.privateKeyFileName">
                        <span class="text-[11px] text-muted-foreground/80">Active Key: </span>
                        <span class="font-mono text-foreground">{{ currentItemDecrypted.privateKeyFileName }}</span>
                    </div>
                    <div v-if="currentItemDecrypted?.sshPassword">
                        <span class="text-[11px] text-muted-foreground/80">SSH Password: </span>
                        <span class="font-mono text-foreground">••••••••••••</span>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex flex-col items-center justify-center py-12 text-muted-foreground">
                <div class="h-7 w-7 animate-spin rounded-full border-2 border-primary border-t-transparent mb-2"></div>
                <p class="text-xs">Loading and decrypting version history...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="fetchError" class="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-xs text-destructive flex items-start gap-2">
                <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                <span>{{ fetchError }}</span>
            </div>

            <!-- Empty State -->
            <div v-else-if="histories.length === 0" class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted mb-3">
                    <Clock class="h-6 w-6 text-muted-foreground/60" />
                </div>
                <p class="font-medium text-foreground text-sm">No Previous Versions Found</p>
                <p class="mt-1 text-xs max-w-sm text-muted-foreground">
                    This item has not been modified since creation. Whenever you or a team member updates passwords or key files, all previous credentials will be preserved here automatically so you never lose access.
                </p>
            </div>

            <!-- History Timeline -->
            <div v-else class="space-y-4 pt-1">
                <div class="flex items-center justify-between text-xs text-muted-foreground border-b border-border/40 pb-1">
                    <span>{{ histories.length }} previous version{{ histories.length > 1 ? 's' : '' }} preserved</span>
                    <span>Zero Data Loss Protection</span>
                </div>

                <div
                    v-for="(entry, index) in histories"
                    :key="entry.id"
                    class="rounded-xl border border-border/70 bg-card p-4 shadow-xs transition-all hover:border-primary/40 space-y-3"
                >
                    <!-- Card Header -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-2.5">
                        <div class="flex items-center gap-2">
                            <Badge variant="secondary" class="font-mono text-[11px]">
                                Version #{{ histories.length - index }}
                            </Badge>
                            <span class="flex items-center gap-1 text-xs font-medium text-foreground">
                                <Clock class="h-3.5 w-3.5 text-muted-foreground" />
                                {{ formatDate(entry.created_at) }}
                            </span>
                        </div>

                        <div class="flex items-center gap-1 text-xs text-muted-foreground">
                            <User class="h-3.5 w-3.5" />
                            <span>{{ entry.user?.name || 'Team Member' }}</span>
                        </div>
                    </div>

                    <!-- Decrypted Credential Details -->
                    <div class="space-y-2.5 text-xs">
                        <!-- Login details -->
                        <template v-if="item.type === 'login'">
                            <div v-if="entry.decrypted.username" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">Old Username:</span>
                                <div class="flex items-center gap-2 font-mono font-medium text-foreground">
                                    <span>{{ entry.decrypted.username }}</span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copyToClipboardWithAutoClear(entry.decrypted.username || '', 'Username')"
                                        title="Copy Username"
                                    >
                                        <Copy class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="entry.decrypted.password" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">Old Password:</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-medium text-foreground">
                                        {{ entry.showPassword ? entry.decrypted.password : '••••••••••••••••' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="entry.showPassword = !entry.showPassword"
                                        :title="entry.showPassword ? 'Hide' : 'Reveal'"
                                    >
                                        <EyeOff v-if="entry.showPassword" class="h-3.5 w-3.5" />
                                        <Eye v-else class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copyToClipboardWithAutoClear(entry.decrypted.password || '', 'Old Password')"
                                        title="Copy Password"
                                    >
                                        <Copy class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="entry.decrypted.url" class="flex items-center justify-between text-muted-foreground px-1">
                                <span>URL:</span>
                                <span class="font-mono truncate max-w-[280px]">{{ entry.decrypted.url }}</span>
                            </div>
                        </template>

                        <!-- Server details -->
                        <template v-else-if="item.type === 'server'">
                            <div v-if="entry.decrypted.host" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">Host / Port:</span>
                                <span class="font-mono font-medium text-foreground">
                                    {{ entry.decrypted.host }}:{{ entry.decrypted.port || '22' }}
                                </span>
                            </div>

                            <div v-if="entry.decrypted.username" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">SSH User:</span>
                                <div class="flex items-center gap-2 font-mono font-medium text-foreground">
                                    <span>{{ entry.decrypted.username }}</span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copyToClipboardWithAutoClear(entry.decrypted.username || '', 'SSH User')"
                                    >
                                        <Copy class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Old SSH Password if present -->
                            <div v-if="entry.decrypted.sshPassword" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">Old SSH Password:</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-medium text-foreground">
                                        {{ entry.showSshPassword ? entry.decrypted.sshPassword : '••••••••••••••••' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="entry.showSshPassword = !entry.showSshPassword"
                                    >
                                        <EyeOff v-if="entry.showSshPassword" class="h-3.5 w-3.5" />
                                        <Eye v-else class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="copyToClipboardWithAutoClear(entry.decrypted.sshPassword || '', 'Old SSH Password')"
                                    >
                                        <Copy class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Old PEM / PPK Key if present -->
                            <div v-if="entry.decrypted.privateKey" class="rounded-lg border border-purple-500/20 bg-purple-500/5 p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <FileKey class="h-4 w-4 text-purple-400" />
                                        <span class="font-semibold text-foreground">
                                            {{ entry.decrypted.privateKeyFileName || 'Archived Key File (.pem / .ppk)' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="h-7 gap-1 text-[11px]"
                                            @click="downloadKeyFile(entry)"
                                        >
                                            <Download class="h-3 w-3" />
                                            <span>Download Key</span>
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0"
                                            @click="copyToClipboardWithAutoClear(entry.decrypted.privateKey || '', 'Private Key')"
                                            title="Copy Private Key"
                                        >
                                            <Copy class="h-3 w-3" />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 w-7 p-0"
                                            @click="entry.showPrivateKey = !entry.showPrivateKey"
                                            title="Toggle Key View"
                                        >
                                            <EyeOff v-if="entry.showPrivateKey" class="h-3 w-3" />
                                            <Eye v-else class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>

                                <div v-if="entry.showPrivateKey" class="mt-2 rounded bg-black/40 p-2 font-mono text-[10px] text-muted-foreground max-h-36 overflow-y-auto whitespace-pre">
                                    {{ entry.decrypted.privateKey }}
                                </div>
                            </div>
                        </template>

                        <!-- Card details -->
                        <template v-else-if="item.type === 'card'">
                            <div v-if="entry.decrypted.cardNumber" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">Card Number:</span>
                                <span class="font-mono font-medium">{{ entry.decrypted.cardNumber }}</span>
                            </div>
                            <div v-if="entry.decrypted.cardholder" class="flex items-center justify-between rounded bg-muted/40 px-2.5 py-1.5">
                                <span class="text-muted-foreground">Cardholder:</span>
                                <span>{{ entry.decrypted.cardholder }}</span>
                            </div>
                        </template>

                        <!-- Note details -->
                        <template v-else-if="item.type === 'note'">
                            <div class="rounded bg-muted/40 p-2 text-foreground font-mono text-[11px] whitespace-pre-wrap max-h-24 overflow-y-auto">
                                {{ entry.decrypted.notes || '(Empty Note)' }}
                            </div>
                        </template>
                    </div>

                    <!-- Footer Restore Action -->
                    <div class="flex items-center justify-between pt-1 border-t border-border/30">
                        <span class="text-[11px] text-muted-foreground">
                            Lost or overwritten credentials can be safely restored anytime.
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="h-7 gap-1.5 text-xs hover:bg-primary hover:text-primary-foreground transition-colors"
                            :disabled="entry.isRestoring"
                            @click="restoreVersion(entry)"
                        >
                            <RotateCcw class="h-3 w-3" :class="{ 'animate-spin': entry.isRestoring }" />
                            <span>{{ entry.isRestoring ? 'Restoring...' : 'Restore This Version' }}</span>
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter class="sm:justify-end pt-2">
                <Button variant="outline" size="sm" @click="emit('update:open', false)">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
