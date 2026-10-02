<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    CreditCard,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Flame,
    History,
    Key,
    Lock,
    Plus,
    RefreshCw,
    Search,
    Send,
    Server,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Star,
    Trash2,
    Unlock,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import ImportExportModal from '@/components/vault/ImportExportModal.vue';
import PasswordGeneratorModal from '@/components/vault/PasswordGeneratorModal.vue';
import SecretShareModal from '@/components/vault/SecretShareModal.vue';
import SecurityAuditView from '@/components/vault/SecurityAuditView.vue';
import VaultActivityModal from '@/components/vault/VaultActivityModal.vue';
import VaultItemDetails from '@/components/vault/VaultItemDetails.vue';
import VaultItemModal from '@/components/vault/VaultItemModal.vue';
import VaultUnlockModal from '@/components/vault/VaultUnlockModal.vue';
import { useVault, type VaultItemData } from '@/composables/useVault';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    isVaultConfigured: boolean;
    stats?: {
        total: number;
        logins: number;
        cards: number;
        notes: number;
        servers: number;
        favorites: number;
        trash: number;
    };
    team: {
        id: number;
        name: string;
        slug: string;
        is_personal: boolean;
    };
}>();

defineOptions({
    layout: (props: { team?: { slug: string; name: string } }) => ({
        breadcrumbs: [
            {
                title: 'Password Vault',
                href: props.team ? dashboard(props.team.slug).url : '/',
            },
        ],
    }),
});

const page = usePage();
const {
    isUnlocked,
    isConfigured,
    setTeamContext,
    checkVaultStatus,
    lock,
    decryptItem,
    copyToClipboardWithAutoClear,
    auditAction,
} = useVault();

// Navigation & Category states
type CategoryFilter =
    | 'all'
    | 'login'
    | 'card'
    | 'note'
    | 'server'
    | 'favorites'
    | 'trash'
    | 'audit';
const currentCategory = ref<CategoryFilter>('all');
const searchQuery = ref('');

// Items state
const items = ref<VaultItemData[]>([]);
const selectedItem = ref<VaultItemData | null>(null);
const isLoadingItems = ref(false);

// Modals
const showUnlockModal = ref(false);
const showItemModal = ref(false);
const editingItem = ref<VaultItemData | null>(null);
const showGeneratorModal = ref(false);
const showShareModal = ref(false);
const sharingItem = ref<VaultItemData | null>(null);
const showImportExportModal = ref(false);
const showActivityModal = ref(false);

// Initialize team context
onMounted(async () => {
    setTeamContext(
        props.team.slug,
        props.team.id,
        props.team.name,
        props.team.is_personal,
    );
    await checkVaultStatus();
    if (!isUnlocked.value) {
        showUnlockModal.value = true;
    } else {
        await loadItems();
    }
});

watch(
    () => props.team.slug,
    async (slug) => {
        setTeamContext(
            slug,
            props.team.id,
            props.team.name,
            props.team.is_personal,
        );
        selectedItem.value = null;
        if (isUnlocked.value) {
            await loadItems();
        }
    },
);

async function onVaultUnlocked() {
    showUnlockModal.value = false;
    await loadItems();
}

async function loadItems() {
    if (!isUnlocked.value) return;

    isLoadingItems.value = true;
    try {
        const isTrash = currentCategory.value === 'trash';
        const url = `/${props.team.slug}/vault/items?trash=${isTrash ? 1 : 0}`;
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) throw new Error('Failed to load items');

        const data = await res.json();
        const rawItems: VaultItemData[] = data.items || [];

        // Decrypt all items in browser
        const decryptedItems = await Promise.all(
            rawItems.map(async (it) => {
                const dec = await decryptItem(it);
                return { ...it, decrypted: dec };
            }),
        );

        items.value = decryptedItems;

        // Auto-select first item or maintain selection
        if (selectedItem.value) {
            const found = items.value.find(
                (i) => i.id === selectedItem.value!.id,
            );
            selectedItem.value = found || items.value[0] || null;
        } else if (items.value.length > 0) {
            selectedItem.value = items.value[0];
        } else {
            selectedItem.value = null;
        }
    } catch (e: any) {
        toast.error('Failed to load credentials.');
    } finally {
        isLoadingItems.value = false;
    }
}

watch(currentCategory, async () => {
    if (currentCategory.value !== 'audit') {
        await loadItems();
    }
});

// Filtered items based on Category and Search Query
const filteredItems = computed(() => {
    let list = items.value;

    if (currentCategory.value === 'favorites') {
        list = list.filter((i) => i.is_favorite);
    } else if (
        ['login', 'card', 'note', 'server'].includes(currentCategory.value)
    ) {
        list = list.filter((i) => i.type === currentCategory.value);
    }

    if (!searchQuery.value.trim()) return list;

    const q = searchQuery.value.toLowerCase();
    return list.filter((it) => {
        const title = (it.decrypted?.title || it.title || '').toLowerCase();
        const username = (it.decrypted?.username || '').toLowerCase();
        const url = (it.decrypted?.url || '').toLowerCase();
        const folder = (it.folder || '').toLowerCase();
        return (
            title.includes(q) ||
            username.includes(q) ||
            url.includes(q) ||
            folder.includes(q)
        );
    });
});

// Category counts
const counts = computed(() => {
    return {
        all: items.value.length,
        login: items.value.filter((i) => i.type === 'login').length,
        card: items.value.filter((i) => i.type === 'card').length,
        note: items.value.filter((i) => i.type === 'note').length,
        server: items.value.filter((i) => i.type === 'server').length,
        favorites: items.value.filter((i) => i.is_favorite).length,
    };
});

// Actions
function handleAddNew() {
    editingItem.value = null;
    showItemModal.value = true;
}

function handleEdit(item: VaultItemData) {
    editingItem.value = item;
    showItemModal.value = true;
}

async function handleItemSaved(item: VaultItemData) {
    await loadItems();
    selectedItem.value = item;
}

async function handleDelete(item: VaultItemData) {
    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content || '';
    try {
        const res = await fetch(`/${props.team.slug}/vault/items/${item.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        if (res.ok) {
            toast.success('Item moved to Trash.');
            await loadItems();
        }
    } catch {
        toast.error('Failed to move item to trash.');
    }
}

async function handleRestore(item: VaultItemData) {
    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content || '';
    try {
        const res = await fetch(
            `/${props.team.slug}/vault/items/${item.id}/restore`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );
        if (res.ok) {
            toast.success('Item restored.');
            await loadItems();
        }
    } catch {
        toast.error('Failed to restore item.');
    }
}

async function handleForceDelete(item: VaultItemData) {
    if (
        !confirm(
            'Are you sure you want to permanently delete this item? This cannot be undone.',
        )
    )
        return;
    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content || '';
    try {
        const res = await fetch(
            `/${props.team.slug}/vault/items/${item.id}/force`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );
        if (res.ok) {
            toast.success('Item permanently deleted.');
            await loadItems();
        }
    } catch {
        toast.error('Failed to delete item.');
    }
}

async function handleFavoriteToggle(item: VaultItemData) {
    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content || '';
    try {
        const res = await fetch(
            `/${props.team.slug}/vault/items/${item.id}/favorite`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );
        if (res.ok) {
            item.is_favorite = !item.is_favorite;
        }
    } catch {
        toast.error('Failed to toggle favorite.');
    }
}

function handleShare(item: VaultItemData) {
    sharingItem.value = item;
    showShareModal.value = true;
}

async function quickCopyPassword(item: VaultItemData, e: Event) {
    e.stopPropagation();
    const pwd = item.decrypted?.password;
    if (pwd) {
        await copyToClipboardWithAutoClear(pwd, 'Password');
        await auditAction('copied_password', item.id, item.title);
    }
}
</script>

<template>
    <Head title="Password Vault" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <!-- Main Container -->
    <div class="flex h-full flex-1 flex-col gap-4 overflow-hidden p-4 lg:p-6">
        <!-- Top Action & Status Bar -->
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-xs sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary shadow-xs"
                >
                    <ShieldCheck
                        v-if="isUnlocked"
                        class="h-6 w-6 text-emerald-500"
                    />
                    <Lock v-else class="h-6 w-6 text-amber-500" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1
                            class="text-lg font-bold tracking-tight text-foreground"
                        >
                            {{
                                team.is_personal
                                    ? 'Personal Vault'
                                    : `${team.name} Vault`
                            }}
                        </h1>
                        <Badge variant="outline" class="gap-1 py-0 text-[11px]">
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    isUnlocked
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500'
                                "
                            />
                            {{
                                isUnlocked
                                    ? 'Zero-Knowledge Unlocked'
                                    : 'Locked'
                            }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        WebCrypto AES-256-GCM client-side encryption. The server
                        stores only encrypted blobs.
                    </p>
                </div>
            </div>

            <!-- Toolbar buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="isUnlocked"
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="showGeneratorModal = true"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    <span class="hidden md:inline">Password Generator</span>
                    <span class="md:hidden">Generator</span>
                </Button>

                <Button
                    v-if="isUnlocked"
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="
                        showShareModal = true;
                        sharingItem = null;
                    "
                >
                    <Flame class="h-3.5 w-3.5 text-orange-500" />
                    <span class="hidden md:inline">Share Secret</span>
                    <span class="md:hidden">Share</span>
                </Button>

                <Button
                    v-if="isUnlocked"
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="showImportExportModal = true"
                >
                    <FileSpreadsheet class="h-3.5 w-3.5" />
                    <span class="hidden lg:inline">Import / Export</span>
                </Button>

                <Button
                    v-if="isUnlocked"
                    variant="outline"
                    size="sm"
                    class="gap-1.5"
                    @click="showActivityModal = true"
                >
                    <History class="h-3.5 w-3.5" />
                    <span class="hidden lg:inline">Activity</span>
                </Button>

                <Button
                    v-if="isUnlocked"
                    size="sm"
                    class="gap-1.5"
                    @click="handleAddNew"
                >
                    <Plus class="h-4 w-4" />
                    <span>New Item</span>
                </Button>

                <Button
                    v-if="isUnlocked"
                    variant="ghost"
                    size="icon"
                    class="h-9 w-9 text-muted-foreground hover:text-foreground"
                    @click="
                        lock();
                        showUnlockModal = true;
                    "
                    title="Lock Vault"
                >
                    <Lock class="h-4 w-4" />
                </Button>

                <Button
                    v-else
                    size="sm"
                    class="gap-1.5"
                    @click="showUnlockModal = true"
                >
                    <Unlock class="h-4 w-4" />
                    <span>Unlock Vault</span>
                </Button>
            </div>
        </div>

        <!-- Category Nav Pill Bar -->
        <div
            class="flex items-center gap-1.5 overflow-x-auto border-b border-border/50 pb-1 text-sm"
        >
            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'all'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'all'"
            >
                <span>All Items</span>
                <span
                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                    :class="
                        currentCategory === 'all'
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    {{ counts.all }}
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'login'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'login'"
            >
                <Key class="h-3.5 w-3.5" />
                <span>Logins</span>
                <span
                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                    :class="
                        currentCategory === 'login'
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    {{ counts.login }}
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'card'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'card'"
            >
                <CreditCard class="h-3.5 w-3.5" />
                <span>Cards</span>
                <span
                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                    :class="
                        currentCategory === 'card'
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    {{ counts.card }}
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'note'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'note'"
            >
                <FileText class="h-3.5 w-3.5" />
                <span>Secure Notes</span>
                <span
                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                    :class="
                        currentCategory === 'note'
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    {{ counts.note }}
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'server'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'server'"
            >
                <Server class="h-3.5 w-3.5" />
                <span>Servers / APIs</span>
                <span
                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                    :class="
                        currentCategory === 'server'
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    {{ counts.server }}
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'favorites'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'favorites'"
            >
                <Star class="h-3.5 w-3.5" />
                <span>Favorites</span>
                <span
                    class="py-0.2 rounded-full px-1.5 text-[11px]"
                    :class="
                        currentCategory === 'favorites'
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    {{ counts.favorites }}
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'audit'
                        ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'audit'"
            >
                <ShieldCheck class="h-3.5 w-3.5 text-emerald-400" />
                <span>Security Audit</span>
            </button>

            <button
                type="button"
                class="ml-auto flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors sm:text-sm"
                :class="
                    currentCategory === 'trash'
                        ? 'bg-destructive font-semibold text-destructive-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="currentCategory = 'trash'"
            >
                <Trash2 class="h-3.5 w-3.5" />
                <span>Trash</span>
            </button>
        </div>

        <!-- AUDIT VIEW TAB -->
        <template v-if="currentCategory === 'audit'">
            <div class="flex-1 overflow-y-auto">
                <SecurityAuditView
                    :items="items"
                    @fixItem="(item) => handleEdit(item)"
                />
            </div>
        </template>

        <!-- VAULT ITEMS MASTER-DETAIL VIEW -->
        <template v-else>
            <div
                class="grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-hidden lg:grid-cols-12"
            >
                <!-- Left Panel: Search & Item List (5 cols) -->
                <div
                    class="flex h-full flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-xs lg:col-span-5"
                >
                    <!-- Search Input -->
                    <div class="border-b border-border/50 bg-muted/20 p-3">
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search by title, username, folder..."
                                class="h-9 bg-background pl-9 text-xs"
                            />
                        </div>
                    </div>

                    <!-- Items List -->
                    <div
                        class="flex-1 divide-y divide-border/40 overflow-y-auto"
                    >
                        <!-- Locked State -->
                        <div
                            v-if="!isUnlocked"
                            class="space-y-3 p-12 text-center"
                        >
                            <Lock
                                class="mx-auto h-10 w-10 text-muted-foreground"
                            />
                            <h3 class="text-base font-bold text-foreground">
                                Vault is Locked
                            </h3>
                            <p
                                class="mx-auto max-w-xs text-xs text-muted-foreground"
                            >
                                Enter your master password to decrypt and view
                                items.
                            </p>
                            <Button
                                size="sm"
                                class="gap-1.5"
                                @click="showUnlockModal = true"
                            >
                                <Unlock class="h-3.5 w-3.5" />
                                <span>Unlock Now</span>
                            </Button>
                        </div>

                        <!-- Loading State -->
                        <div
                            v-else-if="isLoadingItems"
                            class="space-y-2 p-12 text-center"
                        >
                            <Spinner class="mx-auto h-6 w-6 text-primary" />
                            <p class="text-xs text-muted-foreground">
                                Decrypting vault items...
                            </p>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else-if="filteredItems.length === 0"
                            class="space-y-3 p-12 text-center"
                        >
                            <Key
                                class="mx-auto h-10 w-10 text-muted-foreground/60"
                            />
                            <h3 class="text-base font-bold text-foreground">
                                No Items Found
                            </h3>
                            <p
                                class="mx-auto max-w-xs text-xs text-muted-foreground"
                            >
                                {{
                                    searchQuery
                                        ? 'No items match your search filter.'
                                        : 'Your vault is currently empty. Add your first item!'
                                }}
                            </p>
                            <Button
                                v-if="!searchQuery"
                                size="sm"
                                class="gap-1.5"
                                @click="handleAddNew"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <span>Add Credential</span>
                            </Button>
                        </div>

                        <!-- Item Rows -->
                        <div
                            v-else
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="flex cursor-pointer items-center justify-between gap-3 p-3.5 transition-colors"
                            :class="
                                selectedItem?.id === item.id
                                    ? 'border-l-4 border-l-primary bg-primary/10'
                                    : 'hover:bg-muted/30'
                            "
                            @click="selectedItem = item"
                        >
                            <div
                                class="flex items-center gap-3 overflow-hidden"
                            >
                                <!-- Type Icon -->
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                    :class="[
                                        item.type === 'login'
                                            ? 'bg-blue-500/10 text-blue-500'
                                            : item.type === 'card'
                                              ? 'bg-amber-500/10 text-amber-500'
                                              : item.type === 'note'
                                                ? 'bg-emerald-500/10 text-emerald-500'
                                                : 'bg-purple-500/10 text-purple-500',
                                    ]"
                                >
                                    <Key
                                        v-if="item.type === 'login'"
                                        class="h-4 w-4"
                                    />
                                    <CreditCard
                                        v-else-if="item.type === 'card'"
                                        class="h-4 w-4"
                                    />
                                    <FileText
                                        v-else-if="item.type === 'note'"
                                        class="h-4 w-4"
                                    />
                                    <Server v-else class="h-4 w-4" />
                                </div>

                                <div class="overflow-hidden">
                                    <div class="flex items-center gap-1.5">
                                        <h4
                                            class="truncate text-sm font-bold text-foreground"
                                        >
                                            {{
                                                item.decrypted?.title ||
                                                item.title
                                            }}
                                        </h4>
                                        <Star
                                            v-if="item.is_favorite"
                                            class="h-3.5 w-3.5 shrink-0 fill-amber-500 text-amber-500"
                                        />
                                    </div>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            item.decrypted?.username ||
                                            item.decrypted?.url ||
                                            item.folder ||
                                            item.type
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Right Quick Actions -->
                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    v-if="item.decrypted?.password"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    @click="(e) => quickCopyPassword(item, e)"
                                    title="Quick Copy Password"
                                >
                                    <Key class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Item Details (7 cols) -->
                <div class="flex h-full flex-col overflow-hidden lg:col-span-7">
                    <VaultItemDetails
                        v-if="selectedItem"
                        :item="selectedItem"
                        :isTrash="currentCategory === 'trash'"
                        @edit="(it) => handleEdit(it)"
                        @delete="(it) => handleDelete(it)"
                        @restore="(it) => handleRestore(it)"
                        @forceDelete="(it) => handleForceDelete(it)"
                        @share="(it) => handleShare(it)"
                        @favoriteToggle="(it) => handleFavoriteToggle(it)"
                    />
                    <div
                        v-else
                        class="flex h-full flex-col items-center justify-center space-y-3 rounded-xl border border-sidebar-border/70 bg-card p-12 text-center text-muted-foreground"
                    >
                        <Shield class="h-12 w-12 text-muted-foreground/40" />
                        <h3 class="text-base font-bold text-foreground">
                            Select an Item
                        </h3>
                        <p class="max-w-xs text-xs">
                            Select an item from the list on the left to view
                            credentials, copy passwords, or inspect TOTP 2FA
                            codes.
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Modals -->
    <VaultUnlockModal :open="showUnlockModal" @unlocked="onVaultUnlocked" />

    <VaultItemModal
        :open="showItemModal"
        :item="editingItem"
        @update:open="(val) => (showItemModal = val)"
        @saved="handleItemSaved"
    />

    <PasswordGeneratorModal
        :open="showGeneratorModal"
        :standalone="true"
        @update:open="(val) => (showGeneratorModal = val)"
    />

    <SecretShareModal
        :open="showShareModal"
        :initialItem="sharingItem"
        @update:open="(val) => (showShareModal = val)"
    />

    <ImportExportModal
        :open="showImportExportModal"
        :items="items"
        @update:open="(val) => (showImportExportModal = val)"
        @imported="loadItems"
    />

    <VaultActivityModal
        :open="showActivityModal"
        @update:open="(val) => (showActivityModal = val)"
    />
</template>
