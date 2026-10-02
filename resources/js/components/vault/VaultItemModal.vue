<script setup lang="ts">
import {
    CreditCard,
    Eye,
    EyeOff,
    FileText,
    Key,
    Lock,
    Server,
    Sparkles,
    Star,
    Timer,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PasswordGeneratorModal from '@/components/vault/PasswordGeneratorModal.vue';
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
import {
    type DecryptedPayload,
    useVault,
    type VaultItemData,
} from '@/composables/useVault';
import { calculatePasswordStrength, generateTotpCode } from '@/lib/crypto';

const props = defineProps<{
    open: boolean;
    item?: VaultItemData | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'saved', item: VaultItemData): void;
}>();

const { currentTeamSlug, encryptItemPayload } = useVault();

const type = ref<'login' | 'card' | 'note' | 'server'>('login');
const isFavorite = ref(false);
const folder = ref('');
const showPassword = ref(false);
const showGeneratorModal = ref(false);
const isSaving = ref(false);

const form = reactive<DecryptedPayload>({
    title: '',
    username: '',
    password: '',
    url: '',
    notes: '',
    totp: '',
    cardholder: '',
    cardNumber: '',
    expMonth: '',
    expYear: '',
    cvv: '',
    pin: '',
    host: '',
    port: '',
    privateKey: '',
});

// Live TOTP preview if a secret is entered
const totpPreview = ref<string | null>(null);

watch(
    () => form.totp,
    async (secret) => {
        if (!secret || secret.trim().length < 8) {
            totpPreview.value = null;
            return;
        }
        const res = await generateTotpCode(secret);
        totpPreview.value = res.code !== '------' ? res.code : null;
    },
    { immediate: true },
);

// Populate form when item is provided (edit mode)
watch(
    () => props.item,
    (val) => {
        if (val) {
            type.value = val.type;
            isFavorite.value = val.is_favorite;
            folder.value = val.folder || '';
            const dec = val.decrypted || { title: val.title };
            form.title = dec.title || val.title;
            form.username = dec.username || '';
            form.password = dec.password || '';
            form.url = dec.url || '';
            form.notes = dec.notes || '';
            form.totp = dec.totp || '';
            form.cardholder = dec.cardholder || '';
            form.cardNumber = dec.cardNumber || '';
            form.expMonth = dec.expMonth || '';
            form.expYear = dec.expYear || '';
            form.cvv = dec.cvv || '';
            form.pin = dec.pin || '';
            form.host = dec.host || '';
            form.port = dec.port || '';
            form.privateKey = dec.privateKey || '';
        } else {
            resetForm();
        }
    },
    { immediate: true },
);

function resetForm() {
    type.value = 'login';
    isFavorite.value = false;
    folder.value = '';
    showPassword.value = false;
    form.title = '';
    form.username = '';
    form.password = '';
    form.url = '';
    form.notes = '';
    form.totp = '';
    form.cardholder = '';
    form.cardNumber = '';
    form.expMonth = '';
    form.expYear = '';
    form.cvv = '';
    form.pin = '';
    form.host = '';
    form.port = '';
    form.privateKey = '';
}

const passwordStrength = computed(() => {
    return form.password ? calculatePasswordStrength(form.password) : null;
});

async function handleSave() {
    if (!form.title.trim()) {
        toast.error('Item title is required.');
        return;
    }

    isSaving.value = true;
    try {
        const payloadToEncrypt: DecryptedPayload = { ...form };
        const { ciphertext, iv } = await encryptItemPayload(payloadToEncrypt);

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';
        const isEditing = !!props.item?.id;
        const endpoint = isEditing
            ? `/${currentTeamSlug.value}/vault/items/${props.item!.id}`
            : `/${currentTeamSlug.value}/vault/items`;

        const method = isEditing ? 'PUT' : 'POST';

        const res = await fetch(endpoint, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                type: type.value,
                title: form.title,
                encrypted_data: ciphertext,
                iv,
                is_favorite: isFavorite.value,
                folder: folder.value || null,
            }),
        });

        if (!res.ok) {
            const err = await res.json();
            throw new Error(err.message || 'Failed to save item');
        }

        const data = await res.json();
        const savedItem: VaultItemData = {
            ...data.item,
            decrypted: payloadToEncrypt,
        };

        toast.success(
            isEditing ? 'Item updated securely.' : 'Item added to vault.',
        );
        emit('saved', savedItem);
        emit('update:open', false);
    } catch (e: any) {
        toast.error(e.message || 'Error encrypting and saving item.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <DialogTitle class="text-xl font-bold">
                        {{ item ? 'Edit Item' : 'New Vault Item' }}
                    </DialogTitle>
                    <button
                        type="button"
                        class="rounded-full p-1 text-muted-foreground transition-colors hover:text-amber-500"
                        :class="{ 'text-amber-500': isFavorite }"
                        @click="isFavorite = !isFavorite"
                        title="Toggle Favorite"
                    >
                        <Star
                            class="h-5 w-5"
                            :class="{ 'fill-amber-500': isFavorite }"
                        />
                    </button>
                </div>
                <DialogDescription>
                    All sensitive values are encrypted client-side using
                    AES-256-GCM before saving.
                </DialogDescription>
            </DialogHeader>

            <!-- Category Selector Tabs -->
            <div
                class="grid grid-cols-4 gap-1 rounded-lg bg-muted p-1 text-xs font-medium sm:text-sm"
            >
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-md py-1.5 transition-colors"
                    :class="
                        type === 'login'
                            ? 'bg-background font-semibold text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="type = 'login'"
                >
                    <Key class="h-3.5 w-3.5" />
                    <span>Login</span>
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-md py-1.5 transition-colors"
                    :class="
                        type === 'card'
                            ? 'bg-background font-semibold text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="type = 'card'"
                >
                    <CreditCard class="h-3.5 w-3.5" />
                    <span>Card</span>
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-md py-1.5 transition-colors"
                    :class="
                        type === 'note'
                            ? 'bg-background font-semibold text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="type = 'note'"
                >
                    <FileText class="h-3.5 w-3.5" />
                    <span>Note</span>
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-md py-1.5 transition-colors"
                    :class="
                        type === 'server'
                            ? 'bg-background font-semibold text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="type = 'server'"
                >
                    <Server class="h-3.5 w-3.5" />
                    <span>Server</span>
                </button>
            </div>

            <form @submit.prevent="handleSave" class="space-y-4 pt-1">
                <!-- Common Title & Folder -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="item-title">Title / Name *</Label>
                        <Input
                            id="item-title"
                            v-model="form.title"
                            placeholder="e.g. GitHub, AWS Console, Netflix"
                            required
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="item-folder">Folder / Tag</Label>
                        <Input
                            id="item-folder"
                            v-model="folder"
                            placeholder="e.g. Work, Finance"
                        />
                    </div>
                </div>

                <!-- LOGIN FIELDS -->
                <template v-if="type === 'login'">
                    <div class="space-y-1.5">
                        <Label for="item-username">Username or Email</Label>
                        <Input
                            id="item-username"
                            v-model="form.username"
                            placeholder="user@example.com"
                            autocomplete="username"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="item-password">Password</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 gap-1 px-1.5 text-xs text-primary"
                                @click="showGeneratorModal = true"
                            >
                                <Sparkles class="h-3 w-3" />
                                <span>Generate</span>
                            </Button>
                        </div>
                        <div class="relative">
                            <Input
                                id="item-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Enter or generate password"
                                class="pr-10 font-mono text-sm"
                                autocomplete="current-password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Strength meter inline -->
                        <div
                            v-if="passwordStrength && form.password"
                            class="space-y-1 pt-1"
                        >
                            <div
                                class="flex justify-between text-[11px] text-muted-foreground"
                            >
                                <span
                                    >Strength:
                                    <strong :class="passwordStrength.color">{{
                                        passwordStrength.label
                                    }}</strong></span
                                >
                                <span>{{ passwordStrength.entropy }} bits</span>
                            </div>
                            <div
                                class="h-1 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="[
                                        passwordStrength.score === 1
                                            ? 'w-1/4 bg-red-500'
                                            : passwordStrength.score === 2
                                              ? 'w-2/4 bg-orange-500'
                                              : passwordStrength.score === 3
                                                ? 'w-3/4 bg-yellow-500'
                                                : 'w-full bg-emerald-500',
                                    ]"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="item-url">Website URL</Label>
                        <Input
                            id="item-url"
                            v-model="form.url"
                            type="url"
                            placeholder="https://github.com/login"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label
                                for="item-totp"
                                class="flex items-center gap-1.5"
                            >
                                <Timer class="h-3.5 w-3.5 text-primary" />
                                <span>TOTP / Authenticator Key (Base32)</span>
                            </Label>
                            <span
                                v-if="totpPreview"
                                class="rounded bg-emerald-500/10 px-2 py-0.5 font-mono text-xs font-bold text-emerald-500"
                            >
                                Preview: {{ totpPreview }}
                            </span>
                        </div>
                        <Input
                            id="item-totp"
                            v-model="form.totp"
                            placeholder="JBSWY3DPEHPK3PXP"
                            class="font-mono text-sm uppercase"
                        />
                    </div>
                </template>

                <!-- PAYMENT CARD FIELDS -->
                <template v-else-if="type === 'card'">
                    <div class="space-y-1.5">
                        <Label for="card-holder">Cardholder Name</Label>
                        <Input
                            id="card-holder"
                            v-model="form.cardholder"
                            placeholder="ALEX MORGAN"
                            class="uppercase"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="card-number">Card Number</Label>
                        <Input
                            id="card-number"
                            v-model="form.cardNumber"
                            placeholder="4000 1234 5678 9010"
                            class="font-mono text-sm tracking-wider"
                        />
                    </div>

                    <div class="grid grid-cols-4 gap-2">
                        <div class="space-y-1.5">
                            <Label for="card-exp-m">Exp Month</Label>
                            <Input
                                id="card-exp-m"
                                v-model="form.expMonth"
                                placeholder="12"
                                maxlength="2"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="card-exp-y">Exp Year</Label>
                            <Input
                                id="card-exp-y"
                                v-model="form.expYear"
                                placeholder="28"
                                maxlength="4"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="card-cvv">CVV / CVC</Label>
                            <Input
                                id="card-cvv"
                                v-model="form.cvv"
                                type="password"
                                placeholder="123"
                                maxlength="4"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="card-pin">PIN</Label>
                            <Input
                                id="card-pin"
                                v-model="form.pin"
                                type="password"
                                placeholder="••••"
                                maxlength="6"
                            />
                        </div>
                    </div>
                </template>

                <!-- SECURE NOTE FIELDS -->
                <template v-else-if="type === 'note'">
                    <div class="space-y-1.5">
                        <Label for="note-body">Secure Note Content</Label>
                        <textarea
                            id="note-body"
                            v-model="form.notes"
                            rows="6"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="Write private notes, recovery codes, or license keys..."
                        />
                    </div>
                </template>

                <!-- SERVER / API FIELDS -->
                <template v-else-if="type === 'server'">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2 space-y-1.5">
                            <Label for="server-host">Host / IP Address</Label>
                            <Input
                                id="server-host"
                                v-model="form.host"
                                placeholder="ssh.example.com"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="server-port">Port</Label>
                            <Input
                                id="server-port"
                                v-model="form.port"
                                placeholder="22"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="server-user">Username</Label>
                        <Input
                            id="server-user"
                            v-model="form.username"
                            placeholder="root or ubuntu"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="server-key">Private Key / API Secret</Label>
                        <textarea
                            id="server-key"
                            v-model="form.privateKey"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            placeholder="-----BEGIN OPENSSH PRIVATE KEY-----..."
                        />
                    </div>
                </template>

                <!-- Common Notes (except for note type which already has it) -->
                <div v-if="type !== 'note'" class="space-y-1.5">
                    <Label for="item-notes">Notes / Extra Info</Label>
                    <textarea
                        id="item-notes"
                        v-model="form.notes"
                        rows="2"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        placeholder="Additional details, security questions, or notes..."
                    />
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSaving">
                        <Spinner v-if="isSaving" class="mr-2" />
                        {{
                            isSaving
                                ? 'Encrypting & Saving...'
                                : item
                                  ? 'Save Changes'
                                  : 'Encrypt & Save'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Embedded Generator Modal -->
    <PasswordGeneratorModal
        :open="showGeneratorModal"
        @update:open="(val) => (showGeneratorModal = val)"
        @select="(pwd) => (form.password = pwd)"
    />
</template>
