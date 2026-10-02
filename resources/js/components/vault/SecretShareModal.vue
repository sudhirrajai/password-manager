<script setup lang="ts">
import {
    Check,
    Copy,
    Flame,
    Link2,
    Lock,
    Send,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { ref, watch } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';
import { useVault, type VaultItemData } from '@/composables/useVault';
import { bufferToBase64, encryptData, generateVaultKey } from '@/lib/crypto';

const props = defineProps<{
    open: boolean;
    initialItem?: VaultItemData | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { currentTeamSlug, copyToClipboardWithAutoClear } = useVault();

const title = ref('');
const secretContent = ref('');
const expiresInHours = ref(24);
const maxViews = ref(1);
const passphrase = ref('');

const isGenerating = ref(false);
const shareableUrl = ref('');
const isCopied = ref(false);

watch(
    () => props.open,
    (val) => {
        if (val) {
            shareableUrl.value = '';
            isCopied.value = false;
            passphrase.value = '';
            if (props.initialItem) {
                title.value = `Secret for ${props.initialItem.decrypted?.title || props.initialItem.title}`;
                const dec = props.initialItem.decrypted;
                if (dec?.password) {
                    secretContent.value = `Username: ${dec.username || 'N/A'}\nPassword: ${dec.password}`;
                } else if (dec?.notes) {
                    secretContent.value = dec.notes;
                } else {
                    secretContent.value = '';
                }
            } else {
                title.value = '';
                secretContent.value = '';
            }
        }
    },
    { immediate: true },
);

async function handleGenerateLink() {
    if (!secretContent.value.trim()) {
        toast.error('Please enter secret content to share.');
        return;
    }

    isGenerating.value = true;
    try {
        // 1. Generate random ephemeral AES key client-side
        const ephemeralKey = await generateVaultKey();
        const rawKeyBuffer = await window.crypto.subtle.exportKey(
            'raw',
            ephemeralKey,
        );
        const keyBase64 = bufferToBase64(rawKeyBuffer);

        // 2. Encrypt the secret content client-side
        const { ciphertext, iv } = await encryptData(
            secretContent.value,
            ephemeralKey,
        );

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        // 3. Send ciphertext to server
        const res = await fetch(`/${currentTeamSlug.value}/vault/shares`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                title: title.value || 'Encrypted Secret',
                encrypted_data: ciphertext,
                iv,
                expires_in_hours: expiresInHours.value,
                max_views: maxViews.value,
                passphrase: passphrase.value || null,
            }),
        });

        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.message || 'Failed to generate link');
        }

        const data = await res.json();
        const token = data.token;

        // 4. Construct URL with key in the hash fragment (#key=...)
        // Notice: hash fragments are NEVER transmitted to the server!
        shareableUrl.value = `${window.location.origin}/share/${token}#key=${encodeURIComponent(keyBase64)}`;
        toast.success('Zero-knowledge secret link created!');
    } catch (e: any) {
        toast.error(e.message || 'Error generating secret link.');
    } finally {
        isGenerating.value = false;
    }
}

async function handleCopyLink() {
    if (!shareableUrl.value) return;
    await copyToClipboardWithAutoClear(shareableUrl.value, 'Secret Link');
    isCopied.value = true;
    setTimeout(() => {
        isCopied.value = false;
    }, 2500);
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div
                    class="mb-1 flex items-center gap-2 text-sm font-semibold text-primary"
                >
                    <Flame class="h-4 w-4 text-orange-500" />
                    <span>Self-Destructing Secret Link</span>
                </div>
                <DialogTitle class="text-xl font-bold"
                    >One-Time Secure Share</DialogTitle
                >
                <DialogDescription>
                    Share credentials or notes with anyone safely. The
                    decryption key stays strictly inside the URL hash fragment
                    (#) and is never sent to our servers.
                </DialogDescription>
            </DialogHeader>

            <!-- Generated State -->
            <div v-if="shareableUrl" class="space-y-4 py-2">
                <div
                    class="space-y-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4"
                >
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-emerald-600"
                    >
                        <ShieldCheck class="h-5 w-5" />
                        <span>Link Ready to Share!</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        This link will self-destruct once viewed
                        {{ maxViews }} time(s) or after
                        {{ expiresInHours }} hour(s).
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label>Shareable URL</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            :value="shareableUrl"
                            readonly
                            class="bg-muted/40 font-mono text-xs select-all"
                        />
                        <Button
                            class="shrink-0 gap-1.5"
                            @click="handleCopyLink"
                        >
                            <Check
                                v-if="isCopied"
                                class="h-4 w-4 text-emerald-500"
                            />
                            <Copy v-else class="h-4 w-4" />
                            <span>{{
                                isCopied ? 'Copied!' : 'Copy Link'
                            }}</span>
                        </Button>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        variant="outline"
                        class="w-full"
                        @click="emit('update:open', false)"
                    >
                        Done
                    </Button>
                </DialogFooter>
            </div>

            <!-- Form State -->
            <form
                v-else
                @submit.prevent="handleGenerateLink"
                class="space-y-4 py-1"
            >
                <div class="space-y-1.5">
                    <Label for="share-title">Title (Optional)</Label>
                    <Input
                        id="share-title"
                        v-model="title"
                        placeholder="e.g. Database Credentials for Contractor"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="share-content">Secret Content *</Label>
                    <textarea
                        id="share-content"
                        v-model="secretContent"
                        rows="4"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        placeholder="Paste password, API key, or private note..."
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="share-views">Max Views</Label>
                        <select
                            id="share-views"
                            v-model.number="maxViews"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option :value="1">1 view (Self-destruct)</option>
                            <option :value="3">3 views</option>
                            <option :value="5">5 views</option>
                            <option :value="10">10 views</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="share-expiry">Expires After</Label>
                        <select
                            id="share-expiry"
                            v-model.number="expiresInHours"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option :value="1">1 Hour</option>
                            <option :value="24">24 Hours (1 Day)</option>
                            <option :value="72">3 Days</option>
                            <option :value="168">7 Days</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="share-passphrase">Optional Passphrase</Label>
                    <Input
                        id="share-passphrase"
                        v-model="passphrase"
                        type="password"
                        placeholder="Recipient must enter this to view"
                    />
                    <p class="text-[11px] text-muted-foreground">
                        Add a secondary secret that recipient must enter to open
                        the link.
                    </p>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isGenerating">
                        <Spinner v-if="isGenerating" class="mr-2" />
                        Generate Secure Link
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
