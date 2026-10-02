<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    Clock,
    Copy,
    Eye,
    EyeOff,
    Flame,
    Lock,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { base64ToBuffer, decryptData } from '@/lib/crypto';

const token = ref('');
const secretKeyBase64 = ref('');
const title = ref('');
const decryptedContent = ref('');
const expiresAt = ref<string | null>(null);
const isDestroyed = ref(false);

const isLoading = ref(true);
const errorMessage = ref('');
const requiresPassphrase = ref(false);
const passphraseInput = ref('');
const isCopied = ref(false);

onMounted(async () => {
    // Extract token from URL path: /share/:token
    const pathParts = window.location.pathname.split('/');
    token.value = pathParts[pathParts.length - 1];

    // Extract key from URL hash: #key=...
    const hash = window.location.hash;
    const match = hash.match(/key=([^&]+)/);
    if (match) {
        secretKeyBase64.value = decodeURIComponent(match[1]);
    } else {
        errorMessage.value =
            'Invalid share link: decryption key is missing from URL.';
        isLoading.value = false;
        return;
    }

    await fetchAndDecryptSecret();
});

async function fetchAndDecryptSecret(passphrase?: string) {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';
        const url = `/api/secret/${token.value}`;
        const res = await fetch(url, {
            method: passphrase ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: passphrase ? JSON.stringify({ passphrase }) : undefined,
        });

        if (res.status === 401) {
            requiresPassphrase.value = true;
            isLoading.value = false;
            return;
        }

        if (!res.ok) {
            const err = await res.json();
            throw new Error(
                err.error ||
                    'This secret has expired or has already been viewed and destroyed.',
            );
        }

        const data = await res.json();
        title.value = data.title;
        expiresAt.value = data.expires_at;
        isDestroyed.value = data.is_destroyed;

        // Decrypt client-side using the key from hash
        const rawKeyBuffer = base64ToBuffer(secretKeyBase64.value);
        const cryptoKey = await window.crypto.subtle.importKey(
            'raw',
            rawKeyBuffer,
            { name: 'AES-GCM', length: 256 },
            false,
            ['decrypt'],
        );

        const decrypted = await decryptData<string>(
            data.encrypted_data,
            data.iv,
            cryptoKey,
        );
        decryptedContent.value = decrypted;
        requiresPassphrase.value = false;
    } catch (e: any) {
        errorMessage.value =
            e.message || 'Decryption failed or secret destroyed.';
    } finally {
        isLoading.value = false;
    }
}

async function handleCopy() {
    if (!decryptedContent.value) return;
    try {
        await navigator.clipboard.writeText(decryptedContent.value);
        isCopied.value = true;
        toast.success('Secret copied to clipboard!');
        setTimeout(() => {
            isCopied.value = false;
        }, 2000);
    } catch {
        toast.error('Failed to copy.');
    }
}
</script>

<template>
    <Head title="Secure One-Time Secret" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-background via-background to-muted/40 p-4"
    >
        <div class="w-full max-w-lg space-y-6">
            <!-- Brand header -->
            <div
                class="flex items-center justify-center gap-2 text-lg font-bold text-primary"
            >
                <Flame class="h-6 w-6 text-orange-500" />
                <span>Zero-Knowledge One-Time Secret</span>
            </div>

            <!-- Card -->
            <div
                class="space-y-6 rounded-2xl border border-border/70 bg-card p-6 shadow-lg"
            >
                <!-- Loading State -->
                <div v-if="isLoading" class="space-y-3 p-12 text-center">
                    <Spinner class="mx-auto h-8 w-8 text-primary" />
                    <p class="text-sm font-medium text-foreground">
                        Decrypting secret in browser...
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Keys never touch the server.
                    </p>
                </div>

                <!-- Error / Expired State -->
                <div v-else-if="errorMessage" class="space-y-4 p-8 text-center">
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-destructive/10 text-destructive"
                    >
                        <Flame class="h-7 w-7 text-orange-500" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-lg font-bold text-foreground">
                            Secret Unavailable
                        </h3>
                        <p
                            class="mx-auto max-w-xs text-xs text-muted-foreground"
                        >
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>

                <!-- Passphrase Prompt State -->
                <div v-else-if="requiresPassphrase" class="space-y-4">
                    <div class="space-y-1 text-center">
                        <div
                            class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                        >
                            <Lock class="h-6 w-6" />
                        </div>
                        <h3 class="text-lg font-bold text-foreground">
                            Passphrase Protected
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            The sender set a passphrase for this secret.
                        </p>
                    </div>

                    <form
                        @submit.prevent="fetchAndDecryptSecret(passphraseInput)"
                        class="space-y-3"
                    >
                        <div class="space-y-1.5">
                            <Label for="passphrase">Enter Passphrase</Label>
                            <Input
                                id="passphrase"
                                v-model="passphraseInput"
                                type="password"
                                placeholder="Enter passphrase"
                                required
                                autofocus
                            />
                        </div>

                        <Button type="submit" class="w-full">
                            Unlock Secret
                        </Button>
                    </form>
                </div>

                <!-- Decrypted Success State -->
                <div v-else class="space-y-5">
                    <div
                        class="flex items-center justify-between border-b border-border/50 pb-3"
                    >
                        <div>
                            <h3 class="text-lg font-bold text-foreground">
                                {{ title || 'Confidential Secret' }}
                            </h3>
                            <span
                                class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                End-to-End Decrypted with WebCrypto
                            </span>
                        </div>
                        <Badge
                            v-if="isDestroyed"
                            variant="destructive"
                            class="gap-1 text-xs"
                        >
                            <Flame class="h-3 w-3" />
                            Self-Destructed
                        </Badge>
                    </div>

                    <div
                        v-if="isDestroyed"
                        class="flex items-start gap-2 rounded-xl border border-orange-500/20 bg-orange-500/10 p-3 text-xs text-orange-600 dark:text-orange-400"
                    >
                        <Flame class="mt-0.5 h-4 w-4 shrink-0" />
                        <span
                            >This secret has reached its view limit and has been
                            destroyed permanently from the server. Make sure to
                            copy it now!</span
                        >
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Secret Content</Label
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1.5"
                                @click="handleCopy"
                            >
                                <Check
                                    v-if="isCopied"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                                <span>{{
                                    isCopied ? 'Copied!' : 'Copy to Clipboard'
                                }}</span>
                            </Button>
                        </div>

                        <div
                            class="rounded-xl border border-border/80 bg-muted/30 p-4 font-mono text-sm leading-relaxed break-all whitespace-pre-wrap text-foreground shadow-xs select-all"
                        >
                            {{ decryptedContent }}
                        </div>
                    </div>

                    <div class="pt-2 text-center text-xs text-muted-foreground">
                        Protected by client-side zero-knowledge encryption.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
