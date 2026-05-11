<?php
/**
 * includes/profile_helper.php
 * Handles profile image upload logic shared by citizen and admin profile pages.
 * Returns ['path' => string|null, 'error' => string|null]
 */

function handleProfileImageUpload(array $file, ?string $existingImage): array
{
    // No file chosen — keep existing
    if (empty($file['name'])) {
        return ['path' => $existingImage, 'error' => null];
    }

    $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes = 2 * 1024 * 1024; // 2 MB

    if (!in_array($file['type'], $allowed, true)) {
        return ['path' => null, 'error' => 'Profile photo must be JPG, PNG, or WEBP.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['path' => null, 'error' => 'Profile photo must be under 2 MB.'];
    }

    $uploadDir = __DIR__ . '/../assets/uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'profile_' . uniqid('', true) . '.' . strtolower($ext);
    $destPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['path' => null, 'error' => 'Failed to save profile photo. Please try again.'];
    }

    // Delete old profile image if it exists and is not the default
    if ($existingImage && file_exists(__DIR__ . '/../' . $existingImage)) {
        @unlink(__DIR__ . '/../' . $existingImage);
    }

    return ['path' => 'assets/uploads/profiles/' . $fileName, 'error' => null];
}

/**
 * Returns <img> or initials avatar HTML depending on whether user has a profile image.
 */
function avatarHtml(string $name, ?string $imagePath, string $size = '80px', string $fontSize = '1.8rem'): string
{
    if ($imagePath) {
        $src = htmlspecialchars(BASE_URL . '/' . $imagePath);
        return "<img src=\"{$src}\" alt=\"Profile\" style=\"width:{$size};height:{$size};border-radius:50%;object-fit:cover;border:3px solid var(--cir-green);\" />";
    }
    $initial = strtoupper(substr($name, 0, 1));
    return "<div style=\"width:{$size};height:{$size};border-radius:50%;background:linear-gradient(135deg,var(--cir-green),var(--cir-green-dark));
        display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;
        font-weight:800;font-size:{$fontSize};color:#fff;\">{$initial}</div>";
}