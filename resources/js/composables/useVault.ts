import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    decryptData,
    decryptKeyWithKey,
    deriveKeyFromPassword,
    encryptData,
    encryptKeyWithKey,
    generateRandomSalt,
    generateVaultKey,
} from '@/lib/crypto';

// In-memory key storage (never written to localStorage or cookies for zero-knowledge safety)
const userVaultKey = ref<CryptoKey | null>(null);
const teamVaultKeys = ref<Map<number, CryptoKey>>(new Map());
const isUnlocked = ref(false);
const isConfigured = ref(true);
const isTeamVault = ref(false);
const teamName = ref('');
const teamId = ref<number | null>(null);
const currentTeamSlug = ref('');
const autoLockTimer = ref<number | null>(null);
const autoLockMinutes = ref(15);

export interface DecryptedPayload {
    title: string;
    username?: string;
    password?: string;
    url?: string;
    notes?: string;
    totp?: string;
    // Card fields
    cardholder?: string;
    cardNumber?: string;
    expMonth?: string;
    expYear?: string;
    cvv?: string;
    pin?: string;
    // Server fields
    host?: string;
    port?: string;
    privateKey?: string;
    // Generic custom fields
    customFields?: Array<{ label: string; value: string; isSecret?: boolean }>;
}

export interface VaultItemData {
    id: number;
    team_id: number;
    user_id: number;
    type: 'login' | 'card' | 'note' | 'server';
    title: string;
    encrypted_data: string;
    iv: string;
    is_favorite: boolean;
    folder: string | null;
    last_used_at: string | null;
    password_updated_at: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    user?: { id: number; name: string; email: string };
    decrypted?: DecryptedPayload;
}

export function useVault() {
    function resetAutoLockTimer() {
        if (autoLockTimer.value) {
            window.clearTimeout(autoLockTimer.value);
        }
        if (isUnlocked.value) {
            autoLockTimer.value = window.setTimeout(
                () => {
                    lock();
                    toast.info('Vault auto-locked due to inactivity.');
                },
                autoLockMinutes.value * 60 * 1000,
            );
        }
    }

    // Attach activity listeners
    if (typeof window !== 'undefined') {
        const events = ['mousedown', 'keydown', 'touchstart'];
        events.forEach((event) => {
            window.addEventListener(
                event,
                () => {
                    if (isUnlocked.value) resetAutoLockTimer();
                },
                { passive: true },
            );
        });
    }

    function setTeamContext(
        slug: string,
        id: number,
        name: string,
        isPersonal: boolean,
    ) {
        currentTeamSlug.value = slug;
        teamId.value = id;
        teamName.value = name;
        isTeamVault.value = !isPersonal;
    }

    async function checkVaultStatus(): Promise<{
        isConfigured: boolean;
        isUnlocked: boolean;
    }> {
        if (!currentTeamSlug.value)
            return {
                isConfigured: isConfigured.value,
                isUnlocked: isUnlocked.value,
            };

        try {
            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                isConfigured.value = data.is_configured;
                isTeamVault.value = data.is_team_vault;
                teamName.value = data.team_name;
            }
        } catch (e) {
            console.error('Failed to check vault status', e);
        }

        return {
            isConfigured: isConfigured.value,
            isUnlocked: isUnlocked.value,
        };
    }

    /**
     * Unlock vault using Master Password
     */
    async function unlock(masterPassword: string): Promise<boolean> {
        try {
            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok)
                throw new Error('Failed to retrieve vault key configuration');

            const data = await res.json();
            if (!data.is_configured) {
                isConfigured.value = false;
                return false;
            }

            // Derive master key
            const masterKey = await deriveKeyFromPassword(
                masterPassword,
                data.vault_salt,
            );

            // Decrypt User Vault Key (UVK)
            const uvk = await decryptKeyWithKey(
                data.encrypted_vault_key,
                data.vault_key_iv,
                masterKey,
            );

            userVaultKey.value = uvk;

            // If team vault, decrypt Team Vault Key (TVK)
            if (data.is_team_vault && data.team_vault_key) {
                const tvk = await decryptKeyWithKey(
                    data.team_vault_key.encrypted_team_key,
                    data.team_vault_key.team_key_iv,
                    uvk,
                );
                if (teamId.value) {
                    teamVaultKeys.value.set(teamId.value, tvk);
                }
            }

            isUnlocked.value = true;
            resetAutoLockTimer();
            toast.success('Vault unlocked successfully.');
            return true;
        } catch (e) {
            console.error(e);
            toast.error('Invalid Master Password. Decryption failed.');
            return false;
        }
    }

    /**
     * Setup / Initialize new Vault Key (First time user setup)
     */
    async function setupVault(masterPassword: string): Promise<boolean> {
        try {
            const salt = generateRandomSalt(32);
            const masterKey = await deriveKeyFromPassword(masterPassword, salt);
            const uvk = await generateVaultKey();
            const encryptedUvk = await encryptKeyWithKey(uvk, masterKey);

            let encryptedTeamKey: string | undefined;
            let teamKeyIv: string | undefined;

            if (isTeamVault.value) {
                const tvk = await generateVaultKey();
                const encTvk = await encryptKeyWithKey(tvk, uvk);
                encryptedTeamKey = encTvk.encryptedKey;
                teamKeyIv = encTvk.iv;
                if (teamId.value) {
                    teamVaultKeys.value.set(teamId.value, tvk);
                }
            }

            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';

            const res = await fetch(`/${currentTeamSlug.value}/vault/key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    vault_salt: salt,
                    encrypted_vault_key: encryptedUvk.encryptedKey,
                    vault_key_iv: encryptedUvk.iv,
                    encrypted_team_key: encryptedTeamKey,
                    team_key_iv: teamKeyIv,
                }),
            });

            if (!res.ok) {
                const err = await res.json();
                throw new Error(err.message || 'Setup failed');
            }

            userVaultKey.value = uvk;
            isConfigured.value = true;
            isUnlocked.value = true;
            resetAutoLockTimer();
            toast.success('Zero-knowledge vault initialized successfully!');
            return true;
        } catch (e: any) {
            toast.error(e.message || 'Failed to setup vault');
            return false;
        }
    }

    /**
     * Lock the vault immediately
     */
    function lock() {
        userVaultKey.value = null;
        teamVaultKeys.value.clear();
        isUnlocked.value = false;
        if (autoLockTimer.value) {
            window.clearTimeout(autoLockTimer.value);
            autoLockTimer.value = null;
        }
    }

    /**
     * Get the active encryption key for the current team context
     */
    function getActiveKey(): CryptoKey {
        if (
            isTeamVault.value &&
            teamId.value &&
            teamVaultKeys.value.has(teamId.value)
        ) {
            return teamVaultKeys.value.get(teamId.value)!;
        }
        if (!userVaultKey.value) {
            throw new Error(
                'Vault is locked. Unlock before encrypting/decrypting.',
            );
        }
        return userVaultKey.value;
    }

    /**
     * Encrypt an item payload before sending to backend
     */
    async function encryptItemPayload(
        payload: DecryptedPayload,
    ): Promise<{ ciphertext: string; iv: string }> {
        const key = getActiveKey();
        return encryptData(payload, key);
    }

    /**
     * Decrypt a vault item using the active key
     */
    async function decryptItem(item: VaultItemData): Promise<DecryptedPayload> {
        const key = getActiveKey();
        try {
            return await decryptData<DecryptedPayload>(
                item.encrypted_data,
                item.iv,
                key,
            );
        } catch {
            return {
                title: item.title,
                notes: '[Decryption Error: Key mismatch or corrupted data]',
            };
        }
    }

    /**
     * Copy to clipboard with auto-clear security timer (e.g. 30 seconds)
     */
    async function copyToClipboardWithAutoClear(
        text: string,
        label: string,
        timeoutSec = 30,
    ) {
        try {
            await navigator.clipboard.writeText(text);
            toast.success(`${label} copied to clipboard!`, {
                description: `Clipboard will auto-clear in ${timeoutSec} seconds.`,
            });

            setTimeout(async () => {
                try {
                    const currentText = await navigator.clipboard.readText();
                    if (currentText === text) {
                        await navigator.clipboard.writeText('');
                        toast.info(
                            `Clipboard cleared for security (${label}).`,
                        );
                    }
                } catch {
                    // Browser permissions may restrict reading clipboard in background
                }
            }, timeoutSec * 1000);
        } catch {
            toast.error(`Failed to copy ${label} to clipboard.`);
        }
    }

    /**
     * Log an audit action to backend
     */
    async function auditAction(
        action: string,
        itemId?: number,
        itemTitle?: string,
    ) {
        if (!currentTeamSlug.value) return;
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';
        try {
            await fetch(`/${currentTeamSlug.value}/vault/items/audit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    vault_item_id: itemId,
                    action,
                    item_title: itemTitle,
                }),
            });
        } catch {
            // Ignore audit log failure
        }
    }

    return {
        isUnlocked,
        isConfigured,
        isTeamVault,
        teamName,
        teamId,
        currentTeamSlug,
        setTeamContext,
        checkVaultStatus,
        unlock,
        setupVault,
        lock,
        getActiveKey,
        encryptItemPayload,
        decryptItem,
        copyToClipboardWithAutoClear,
        auditAction,
    };
}
