<script setup lang="ts">
import { Clock, History, Shield, User } from '@lucide/vue';
import { ref, watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
import { useVault } from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { currentTeamSlug } = useVault();

interface AuditLog {
    id: number;
    action: string;
    item_title: string | null;
    ip_address: string | null;
    created_at: string;
    user?: { name: string; email: string };
}

const logs = ref<AuditLog[]>([]);
const isLoading = ref(false);

async function fetchLogs() {
    if (!currentTeamSlug.value) return;
    isLoading.value = true;
    try {
        const res = await fetch(`/${currentTeamSlug.value}/vault/audit-logs`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            logs.value = data.logs || [];
        }
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.open,
    (val) => {
        if (val) fetchLogs();
    },
);

function formatAction(action: string): { label: string; color: string } {
    switch (action) {
        case 'copied_password':
            return {
                label: 'Copied Password',
                color: 'bg-amber-500/10 text-amber-500 border-amber-500/20',
            };
        case 'copied_username':
            return {
                label: 'Copied Username',
                color: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
            };
        case 'copied_totp_code':
            return {
                label: 'Copied TOTP',
                color: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            };
        case 'created':
            return {
                label: 'Created Item',
                color: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            };
        case 'updated':
            return {
                label: 'Updated Item',
                color: 'bg-primary/10 text-primary border-primary/20',
            };
        case 'deleted':
            return {
                label: 'Moved to Trash',
                color: 'bg-red-500/10 text-red-500 border-red-500/20',
            };
        case 'restored':
            return {
                label: 'Restored',
                color: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
            };
        default:
            return {
                label: action.replace(/_/g, ' '),
                color: 'bg-muted text-muted-foreground',
            };
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="flex max-h-[85vh] flex-col sm:max-w-xl">
            <DialogHeader>
                <div
                    class="mb-1 flex items-center gap-2 text-sm font-semibold text-primary"
                >
                    <History class="h-4 w-4" />
                    <span>Audit & Access Trail</span>
                </div>
                <DialogTitle class="text-xl font-bold"
                    >Vault Activity Log</DialogTitle
                >
                <DialogDescription>
                    All secret access, copy operations, modifications, and
                    deletions are recorded for security and compliance.
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex-1 space-y-3 divide-y divide-border/40 overflow-y-auto py-2"
            >
                <div v-if="isLoading" class="p-8 text-center">
                    <Spinner class="mx-auto h-6 w-6" />
                    <p class="mt-2 text-xs text-muted-foreground">
                        Loading audit logs...
                    </p>
                </div>

                <div
                    v-else-if="logs.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No activity recorded yet.
                </div>

                <div
                    v-else
                    v-for="log in logs"
                    :key="log.id"
                    class="flex items-start justify-between gap-3 pt-3 text-xs first:pt-0"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-foreground">{{
                                log.item_title || 'Vault Item'
                            }}</span>
                            <Badge
                                :class="formatAction(log.action).color"
                                class="border py-0 text-[10px]"
                            >
                                {{ formatAction(log.action).label }}
                            </Badge>
                        </div>
                        <div
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <span
                                >By:
                                <strong>{{
                                    log.user?.name || 'User'
                                }}</strong></span
                            >
                            <span v-if="log.ip_address"
                                >• IP: {{ log.ip_address }}</span
                            >
                        </div>
                    </div>

                    <span
                        class="shrink-0 font-mono text-[11px] text-muted-foreground"
                    >
                        {{ new Date(log.created_at).toLocaleString() }}
                    </span>
                </div>
            </div>

            <DialogFooter class="border-t border-border/40 pt-2">
                <Button
                    variant="outline"
                    class="w-full"
                    @click="emit('update:open', false)"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
