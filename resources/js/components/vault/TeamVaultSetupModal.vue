<script setup lang="ts">
import {
    Check,
    Copy,
    Eye,
    EyeOff,
    Key,
    Link,
    Shield,
    Sparkles,
    Users,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useVault } from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
    teamName: string;
    teamSlug: string;
    canManage: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'configured'): void;
}>();

const { isTeamKeyConfigured, setupTeamVault, copyToClipboardWithAutoClear } = useVault();

const passphrase = ref('');
const showPassphrase = ref(false);
const isSaving = ref(false);
const activeKeyDisplay = ref<string>('');

function generateKey() {
    const chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    let p1 = '';
    let p2 = '';
    let p3 = '';
    const array = new Uint8Array(12);
    window.crypto.getRandomValues(array);
    for (let i = 0; i < 4; i++) p1 += chars[array[i] % chars.length];
    for (let i = 4; i < 8; i++) p2 += chars[array[i] % chars.length];
    for (let i = 8; i < 12; i++) p3 += chars[array[i] % chars.length];
    passphrase.value = `team-${p1}-${p2}-${p3}`;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && !isTeamKeyConfigured.value && !passphrase.value) {
            generateKey();
        }
    },
);

const joinLink = computed(() => {
    if (typeof window === 'undefined') return '';
    const key = activeKeyDisplay.value || passphrase.value;
    if (!key) return '';
    return `${window.location.origin}/${props.teamSlug}/dashboard#team_key=${encodeURIComponent(key)}`;
});

async function handleSave() {
    if (!passphrase.value.trim()) {
        toast.error('Please enter or generate a Team Access Key.');
        return;
    }

    isSaving.value = true;
    try {
        const success = await setupTeamVault(passphrase.value.trim());
        if (success) {
            activeKeyDisplay.value = passphrase.value.trim();
            emit('configured');
        }
    } finally {
        isSaving.value = false;
    }
}

function copyJoinLink() {
    if (!joinLink.value) return;
    copyToClipboardWithAutoClear(joinLink.value, '1-Click Team Join Link', 120);
}

function copyPassphrase() {
    const key = activeKeyDisplay.value || passphrase.value;
    if (!key) return;
    copyToClipboardWithAutoClear(key, 'Team Access Key', 60);
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Users class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold">
                            {{ isTeamKeyConfigured ? 'Team Vault Access & Sharing' : 'Configure Shared Team Vault' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs">
                            Allow team members to view and share passwords in {{ teamName }} with zero-knowledge encryption.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- State 1: Team Key is already configured -> View / Share Access -->
            <div v-if="isTeamKeyConfigured" class="space-y-4 py-2">
                <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-400 flex items-start gap-2.5">
                    <Shield class="h-4 w-4 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold">Team Vault Encryption Active</p>
                        <p class="mt-0.5 text-muted-foreground">
                            All passwords in this workspace are protected by a shared Team Vault Key. Invited members can link this workspace to their accounts using the 1-click link or the Team Access Key below.
                        </p>
                    </div>
                </div>

                <!-- 1-Click Join Link -->
                <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                            <Link class="h-3.5 w-3.5 text-primary" />
                            1-Click Team Member Join Link
                        </Label>
                        <Badge variant="outline" class="text-[10px]">Zero-Knowledge Secure</Badge>
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Send this link to invited team members. When they click it, their account will automatically link to the team vault without needing to type anything.
                    </p>
                    <div class="flex items-center gap-2">
                        <Input
                            :value="joinLink || 'Enter team key below to generate link'"
                            readonly
                            class="font-mono text-xs bg-muted/40 select-all"
                        />
                        <Button
                            type="button"
                            size="sm"
                            class="shrink-0 gap-1.5"
                            :disabled="!joinLink"
                            @click="copyJoinLink"
                        >
                            <Copy class="h-3.5 w-3.5" />
                            <span>Copy Link</span>
                        </Button>
                    </div>
                </div>

                <!-- Team Passphrase / Access Code -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-semibold text-foreground">
                            Team Access Key
                        </Label>
                        <span class="text-[11px] text-muted-foreground">For manual entry</span>
                    </div>
                    <div class="relative">
                        <Input
                            :type="showPassphrase ? 'text' : 'password'"
                            v-model="passphrase"
                            placeholder="Enter or paste your team access key"
                            class="font-mono text-xs pr-20"
                        />
                        <div class="absolute right-1.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            <button
                                type="button"
                                class="p-1 text-muted-foreground hover:text-foreground"
                                @click="showPassphrase = !showPassphrase"
                            >
                                <EyeOff v-if="showPassphrase" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="p-1 text-muted-foreground hover:text-foreground"
                                @click="copyPassphrase"
                                title="Copy Key"
                            >
                                <Copy class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="canManage" class="rounded-lg border border-border/40 bg-muted/20 p-3 text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">Need to rotate the team key?</span> Enter a new passphrase above and click Update below. (Note: Existing members will need the new key to re-link).
                </div>
            </div>

            <!-- State 2: Not yet configured -> Initial setup -->
            <div v-else class="space-y-4 py-2">
                <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400 space-y-1">
                    <p class="font-semibold flex items-center gap-1.5">
                        <Sparkles class="h-4 w-4" />
                        First-Time Workspace Setup Required
                    </p>
                    <p class="text-muted-foreground">
                        To enable shared password access for your team members, create a Team Access Key. Your browser will securely wrap the team encryption key with this access code. Existing items will remain 100% accessible.
                    </p>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-semibold text-foreground">
                            Team Access Key / Passphrase
                        </Label>
                        <button
                            type="button"
                            class="text-xs text-primary hover:underline flex items-center gap-1"
                            @click="generateKey"
                        >
                            <Sparkles class="h-3 w-3" />
                            Generate Key
                        </button>
                    </div>
                    <div class="relative">
                        <Input
                            :type="showPassphrase ? 'text' : 'password'"
                            v-model="passphrase"
                            placeholder="e.g. team-xxxx-xxxx-xxxx or custom passphrase"
                            class="font-mono text-xs pr-10"
                        />
                        <button
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="showPassphrase = !showPassphrase"
                        >
                            <EyeOff v-if="showPassphrase" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Keep this key safe. You will share this once with invited team members so they can decrypt and access this shared vault.
                    </p>
                </div>
            </div>

            <DialogFooter class="sm:justify-between pt-3 border-t border-border/40">
                <Button variant="outline" size="sm" @click="emit('update:open', false)">
                    Close
                </Button>
                <Button
                    v-if="canManage"
                    size="sm"
                    class="gap-1.5"
                    :disabled="isSaving || !passphrase.trim()"
                    @click="handleSave"
                >
                    <Key class="h-3.5 w-3.5" />
                    <span>{{ isTeamKeyConfigured ? 'Update Team Key' : 'Activate Team Vault' }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
