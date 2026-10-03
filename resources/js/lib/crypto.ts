/**
 * Zero-Knowledge Cryptographic Operations using native WebCrypto API (AES-GCM + PBKDF2 + HMAC-SHA1)
 */

// Helpers for Base64 and ArrayBuffer conversion
export function bufferToBase64(buffer: ArrayBuffer | Uint8Array): string {
    const bytes =
        buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let binary = '';
    const len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

export function base64ToBuffer(base64: string): ArrayBuffer {
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

export function generateRandomSalt(length = 32): string {
    const saltBytes = new Uint8Array(length);
    window.crypto.getRandomValues(saltBytes);
    return bufferToBase64(saltBytes);
}

/**
 * Derive Master Key from password + salt using PBKDF2-SHA256
 */
export async function deriveKeyFromPassword(
    password: string,
    saltBase64: string,
    iterations = 100000,
): Promise<CryptoKey> {
    const enc = new TextEncoder();
    const passwordBuffer = enc.encode(password);
    const saltBuffer = base64ToBuffer(saltBase64);

    const baseKey = await window.crypto.subtle.importKey(
        'raw',
        passwordBuffer,
        'PBKDF2',
        false,
        ['deriveKey'],
    );

    return window.crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: saltBuffer,
            iterations,
            hash: 'SHA-256',
        },
        baseKey,
        {
            name: 'AES-GCM',
            length: 256,
        },
        false,
        ['encrypt', 'decrypt'],
    );
}

/**
 * Generate a random 256-bit AES-GCM Vault Key (UVK or TVK)
 */
export async function generateVaultKey(): Promise<CryptoKey> {
    return window.crypto.subtle.generateKey(
        {
            name: 'AES-GCM',
            length: 256,
        },
        true, // exportable so it can be encrypted & backed up
        ['encrypt', 'decrypt'],
    );
}

/**
 * Encrypt a Vault Key with another Key (e.g. UVK encrypted by derived Master Key)
 */
export async function encryptKeyWithKey(
    keyToEncrypt: CryptoKey,
    wrappingKey: CryptoKey,
): Promise<{ encryptedKey: string; iv: string }> {
    const exportedRawKey = await window.crypto.subtle.exportKey(
        'raw',
        keyToEncrypt,
    );
    const iv = new Uint8Array(12);
    window.crypto.getRandomValues(iv);

    const ciphertext = await window.crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv,
        },
        wrappingKey,
        exportedRawKey,
    );

    return {
        encryptedKey: bufferToBase64(ciphertext),
        iv: bufferToBase64(iv),
    };
}

/**
 * Decrypt a Vault Key wrapped with another Key
 */
export async function decryptKeyWithKey(
    encryptedKeyBase64: string,
    ivBase64: string,
    unwrappingKey: CryptoKey,
): Promise<CryptoKey> {
    const ciphertext = base64ToBuffer(encryptedKeyBase64);
    const iv = base64ToBuffer(ivBase64);

    const decryptedRawKey = await window.crypto.subtle.decrypt(
        {
            name: 'AES-GCM',
            iv: new Uint8Array(iv),
        },
        unwrappingKey,
        ciphertext,
    );

    return window.crypto.subtle.importKey(
        'raw',
        decryptedRawKey,
        {
            name: 'AES-GCM',
            length: 256,
        },
        true,
        ['encrypt', 'decrypt'],
    );
}

/**
 * Encrypt arbitrary JSON data / object with an AES-GCM key
 */
export async function encryptData(
    data: unknown,
    key: CryptoKey,
): Promise<{ ciphertext: string; iv: string }> {
    const jsonString = JSON.stringify(data);
    const enc = new TextEncoder();
    const encoded = enc.encode(jsonString);

    const iv = new Uint8Array(12);
    window.crypto.getRandomValues(iv);

    const ciphertext = await window.crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv,
        },
        key,
        encoded,
    );

    return {
        ciphertext: bufferToBase64(ciphertext),
        iv: bufferToBase64(iv),
    };
}

/**
 * Decrypt ciphertext with an AES-GCM key
 */
export async function decryptData<T = any>(
    ciphertextBase64: string,
    ivBase64: string,
    key: CryptoKey,
): Promise<T> {
    const ciphertext = base64ToBuffer(ciphertextBase64);
    const iv = base64ToBuffer(ivBase64);

    const decryptedBuffer = await window.crypto.subtle.decrypt(
        {
            name: 'AES-GCM',
            iv: new Uint8Array(iv),
        },
        key,
        ciphertext,
    );

    const dec = new TextDecoder();
    const jsonString = dec.decode(decryptedBuffer);
    return JSON.parse(jsonString) as T;
}

// -------------------------------------------------------------
// Cryptographically Secure Password & Passphrase Generator
// -------------------------------------------------------------

export interface PasswordGeneratorOptions {
    length: number;
    uppercase: boolean;
    lowercase: boolean;
    numbers: boolean;
    symbols: boolean;
    avoidAmbiguous: boolean;
    mode: 'password' | 'passphrase';
    wordsCount?: number;
    separator?: string;
}

const UPPERCASE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const LOWERCASE_CHARS = 'abcdefghijklmnopqrstuvwxyz';
const NUMBER_CHARS = '0123456789';
const SYMBOL_CHARS = '!@#$%^&*()-_=+[]{}|;:,.<>?';
const AMBIGUOUS_CHARS = 'l1Io0O';

const WORD_LIST = [
    'amber',
    'anchor',
    'arcade',
    'beacon',
    'breeze',
    'bullet',
    'cactus',
    'canvas',
    'carbon',
    'castle',
    'cherry',
    'cipher',
    'cobalt',
    'cosmos',
    'canyon',
    'crystal',
    'dagger',
    'delta',
    'dragon',
    'ember',
    'falcon',
    'feather',
    'forest',
    'fossil',
    'galaxy',
    'glacier',
    'gravity',
    'harbor',
    'helix',
    'horizon',
    'hybrid',
    'island',
    'jaguar',
    'jungle',
    'jupiter',
    'karma',
    'lagoon',
    'legend',
    'lotus',
    'lunar',
    'matrix',
    'meteor',
    'mirage',
    'monarch',
    'nebula',
    'nexus',
    'oasis',
    'orbit',
    'oxygen',
    'phoenix',
    'planet',
    'prism',
    'pulse',
    'pyramid',
    'quantum',
    'radar',
    'radius',
    'raven',
    'rebel',
    'rocket',
    'safari',
    'shadow',
    'signal',
    'silver',
    'solar',
    'spiral',
    'summit',
    'thunder',
    'titan',
    'tornado',
    'tracer',
    'vortex',
    'voyage',
    'vulcan',
    'walnut',
    'zenith',
    'zephyr',
    'arctic',
    'aurora',
    'blizzard',
];

export function generatePassword(options: PasswordGeneratorOptions): string {
    if (options.mode === 'passphrase') {
        const count = options.wordsCount || 4;
        const separator = options.separator ?? '-';
        const words: string[] = [];
        const randomValues = new Uint32Array(count);
        window.crypto.getRandomValues(randomValues);

        for (let i = 0; i < count; i++) {
            const index = randomValues[i] % WORD_LIST.length;
            words.push(WORD_LIST[index]);
        }
        return words.join(separator);
    }

    let charset = '';
    const guaranteed: string[] = [];

    let upper = UPPERCASE_CHARS;
    let lower = LOWERCASE_CHARS;
    let numbers = NUMBER_CHARS;
    let symbols = SYMBOL_CHARS;

    if (options.avoidAmbiguous) {
        upper = upper
            .split('')
            .filter((c) => !AMBIGUOUS_CHARS.includes(c))
            .join('');
        lower = lower
            .split('')
            .filter((c) => !AMBIGUOUS_CHARS.includes(c))
            .join('');
        numbers = numbers
            .split('')
            .filter((c) => !AMBIGUOUS_CHARS.includes(c))
            .join('');
    }

    if (options.uppercase) {
        charset += upper;
        guaranteed.push(getRandomChar(upper));
    }
    if (options.lowercase) {
        charset += lower;
        guaranteed.push(getRandomChar(lower));
    }
    if (options.numbers) {
        charset += numbers;
        guaranteed.push(getRandomChar(numbers));
    }
    if (options.symbols) {
        charset += symbols;
        guaranteed.push(getRandomChar(symbols));
    }

    if (!charset) {
        charset = lower + numbers;
    }

    const remainingLength = Math.max(0, options.length - guaranteed.length);
    const randomArray = new Uint32Array(remainingLength);
    window.crypto.getRandomValues(randomArray);

    const chars = [...guaranteed];
    for (let i = 0; i < remainingLength; i++) {
        chars.push(charset[randomArray[i] % charset.length]);
    }

    // Shuffle characters using Fisher-Yates
    const shuffleArray = new Uint32Array(chars.length);
    window.crypto.getRandomValues(shuffleArray);
    for (let i = chars.length - 1; i > 0; i--) {
        const j = shuffleArray[i] % (i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars.join('');
}

function getRandomChar(str: string): string {
    const arr = new Uint32Array(1);
    window.crypto.getRandomValues(arr);
    return str[arr[0] % str.length];
}

// -------------------------------------------------------------
// Real-Time Password Strength & Entropy Analyzer
// -------------------------------------------------------------

export interface PasswordStrengthResult {
    score: number; // 0 (very weak) to 4 (very strong)
    label: string;
    entropy: number;
    color: string;
    crackTime: string;
    suggestions: string[];
}

export function calculatePasswordStrength(
    password: string,
): PasswordStrengthResult {
    if (!password) {
        return {
            score: 0,
            label: 'Empty',
            entropy: 0,
            color: 'bg-muted text-muted-foreground',
            crackTime: 'Instant',
            suggestions: ['Enter a password to test its security.'],
        };
    }

    let pool = 0;
    if (/[a-z]/.test(password)) pool += 26;
    if (/[A-Z]/.test(password)) pool += 26;
    if (/[0-9]/.test(password)) pool += 10;
    if (/[^a-zA-Z0-9]/.test(password)) pool += 33;

    // Shannon entropy formula: E = L * log2(R)
    const entropy =
        pool > 0 ? Math.round(password.length * Math.log2(pool)) : 0;

    const suggestions: string[] = [];
    if (password.length < 12) suggestions.push('Use at least 12 characters.');
    if (!/[A-Z]/.test(password)) suggestions.push('Add uppercase letters.');
    if (!/[0-9]/.test(password)) suggestions.push('Include numbers.');
    if (!/[^a-zA-Z0-9]/.test(password))
        suggestions.push('Include special symbols.');

    let score = 0;
    let label = 'Very Weak';
    let color = 'text-red-500 bg-red-500';
    let crackTime = 'A few seconds';

    if (entropy < 30 || password.length < 8) {
        score = 1;
        label = 'Very Weak';
        color = 'text-red-500 bg-red-500';
        crackTime = 'A few seconds';
    } else if (entropy < 50 || password.length < 10) {
        score = 2;
        label = 'Weak';
        color = 'text-orange-500 bg-orange-500';
        crackTime = 'Few minutes to days';
    } else if (entropy < 70 || password.length < 14) {
        score = 3;
        label = 'Good';
        color = 'text-yellow-500 bg-yellow-500';
        crackTime = 'Months to years';
    } else {
        score = 4;
        label = 'Very Strong';
        color = 'text-emerald-500 bg-emerald-500';
        crackTime = 'Centuries / Billions of years';
    }

    return {
        score,
        label,
        entropy,
        color,
        crackTime,
        suggestions,
    };
}

// -------------------------------------------------------------
// RFC 6238 TOTP (Two-Factor Authenticator) Generator
// -------------------------------------------------------------

function base32Decode(base32: string): Uint8Array {
    const clean = base32.replace(/[\s=-]/g, '').toUpperCase();
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = 0;
    let value = 0;
    const output: number[] = [];

    for (let i = 0; i < clean.length; i++) {
        const val = alphabet.indexOf(clean[i]);
        if (val === -1) continue;
        value = (value << 5) | val;
        bits += 5;
        if (bits >= 8) {
            output.push((value >>> (bits - 8)) & 255);
            bits -= 8;
        }
    }

    return new Uint8Array(output);
}

export async function generateTotpCode(
    secret: string,
    period = 30,
): Promise<{ code: string; remainingSeconds: number }> {
    try {
        const epochSeconds = Math.floor(Date.now() / 1000);
        const timeCounter = Math.floor(epochSeconds / period);
        const remainingSeconds = period - (epochSeconds % period);

        const keyBytes = base32Decode(secret);
        if (keyBytes.length === 0) {
            return { code: '------', remainingSeconds: 0 };
        }

        const hmacKey = await window.crypto.subtle.importKey(
            'raw',
            keyBytes as unknown as BufferSource,
            { name: 'HMAC', hash: 'SHA-1' },
            false,
            ['sign'],
        );

        // 8-byte big-endian counter
        const counterBuffer = new ArrayBuffer(8);
        const counterView = new DataView(counterBuffer);
        counterView.setUint32(4, timeCounter, false);

        const signature = await window.crypto.subtle.sign(
            'HMAC',
            hmacKey,
            counterBuffer,
        );
        const hash = new Uint8Array(signature);

        // Dynamic truncation (RFC 4226)
        const offset = hash[hash.length - 1] & 0x0f;
        const binary =
            ((hash[offset] & 0x7f) << 24) |
            ((hash[offset + 1] & 0xff) << 16) |
            ((hash[offset + 2] & 0xff) << 8) |
            (hash[offset + 3] & 0xff);

        const otp = (binary % 1000000).toString().padStart(6, '0');
        return { code: otp, remainingSeconds };
    } catch {
        return { code: '------', remainingSeconds: 0 };
    }
}

// -------------------------------------------------------------
// Vault Master Recovery Key Generator & Deriver
// -------------------------------------------------------------

const RECOVERY_CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

export function generateRecoveryKey(): { formattedKey: string; rawKey: string } {
    const bytes = new Uint8Array(24);
    window.crypto.getRandomValues(bytes);
    let raw = '';
    for (let i = 0; i < 24; i++) {
        raw += RECOVERY_CHARSET[bytes[i] % RECOVERY_CHARSET.length];
    }

    // Format in blocks of 4: XXXX-XXXX-XXXX-XXXX-XXXX-XXXX
    const formatted = raw.match(/.{1,4}/g)?.join('-') || raw;
    return {
        formattedKey: formatted,
        rawKey: raw,
    };
}

export function normalizeRecoveryKey(key: string): string {
    return key.replace(/[\s-]/g, '').toUpperCase();
}

export async function deriveKeyFromRecoveryKey(
    recoveryKey: string,
    saltBase64: string,
    iterations = 100000,
): Promise<CryptoKey> {
    const cleanKey = normalizeRecoveryKey(recoveryKey);
    return deriveKeyFromPassword(cleanKey, saltBase64, iterations);
}

