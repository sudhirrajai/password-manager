<script setup lang="ts">
import {
    AlertCircle,
    Check,
    Eye,
    EyeOff,
    Key,
    Lock,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useVault } from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
    teamName: string;
    initialKey?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'linked'): void;
}>();

const { linkTeamVaultWithPassphrase } = useVault();

const enteredKey = ref('');
const showKey = ref(false);
const isLinking = ref(false);
const errorMessage = ref<string | null>(null);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            errorMessage.value = null;
            if (props.initialKey) {
                enteredKey.value = props.initialKey;
            }
        }
    },
    { immediate: true },
);

async function handleLink() {
    if (!enteredKey.value.trim()) {
        toast.error('Please enter the Team Access Key.');
        return;
    }

    isLinking.value = true;
    errorMessage.value = null;

    try {
        const success = await linkTeamVaultWithPassphrase(enteredKey.value.trim());
        if (success) {
            emit('linked');
            emit('update:open', false);
        } else {
            errorMessage.value = 'Incorrect Team Access Key or Passphrase. Please confirm with your workspace admin.';
        }
    } catch (e: any) {
        errorMessage.value = e.message || 'Failed to link team vault.';
    } finally {
        isLinking.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Lock class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold">Link Shared Team Vault</DialogTitle>
                        <DialogDescription class="text-xs">
                            Access credentials shared in {{ teamName }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs text-muted-foreground flex items-start gap-2.5">
                    <ShieldCheck class="h-4 w-4 shrink-0 text-primary mt-0.5" />
                    <div>
                        <p class="font-medium text-foreground">Zero-Knowledge Team Protection</p>
                        <p class="mt-0.5 leading-relaxed">
                            This workspace is secured with a shared Team Vault Key. Enter the Team Access Key or Passphrase provided by your workspace admin to link it to your account.
                        </p>
                    </div>
                </div>

                <div v-if="errorMessage" class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive flex items-start gap-2">
                    <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                    <span>{{ errorMessage }}</span>
                </div>

                <form @submit.prevent="handleLink" class="space-y-3">
                    <div class="space-y-1.5">
                        <Label for="team-access-key" class="text-xs font-semibold">
                            Team Access Key or Passphrase
                        </Label>
                        <div class="relative">
                            <Input
                                id="team-access-key"
                                :type="showKey ? 'text' : 'password'"
                                v-model="enteredKey"
                                placeholder="Paste Team Access Key or Passphrase"
                                class="font-mono text-xs pr-10"
                                autofocus
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showKey = !showKey"
                            >
                                <EyeOff v-if="showKey" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            You only need to link this workspace once. It will stay permanently linked to your account.
                        </p>
                    </div>
                </form>
            </div>

            <DialogFooter class="sm:justify-between pt-2 border-t border-border/40">
                <Button variant="outline" size="sm" @click="emit('update:open', false)">
                    Cancel
                </Button>
                <Button
                    size="sm"
                    class="gap-1.5"
                    :disabled="isLinking || !enteredKey.trim()"
                    @click="handleLink"
                >
                    <Key class="h-3.5 w-3.5" />
                    <span>{{ isLinking ? 'Linking...' : 'Link & Access Vault' }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
