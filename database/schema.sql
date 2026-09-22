CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    is_confirmed BOOLEAN NOT NULL DEFAULT FALSE,

    confirmation_token_hash CHAR(64) DEFAULT NULL,
    confirmation_expires_at DATETIME DEFAULT NULL,

    reset_token_hash CHAR(64) DEFAULT NULL,
    reset_expires_at DATETIME DEFAULT NULL,

    comment_notifications BOOLEAN NOT NULL DEFAULT TRUE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),

    UNIQUE KEY uq_users_confirmation_token (
        confirmation_token_hash
    ),

    UNIQUE KEY uq_users_reset_token (
        reset_token_hash
    )
);


CREATE TABLE overlays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_overlays_filename (filename)
);


CREATE TABLE images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_images_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_images_filename (filename),

    INDEX idx_images_created_at (created_at),
    INDEX idx_images_user_id (user_id)
);


CREATE TABLE likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    image_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_likes_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_likes_image
        FOREIGN KEY (image_id)
        REFERENCES images(id)
        ON DELETE CASCADE,

    UNIQUE KEY uq_likes_user_image (user_id, image_id),

    INDEX idx_likes_image_id (image_id)
);


CREATE TABLE comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    image_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_comments_image
        FOREIGN KEY (image_id)
        REFERENCES images(id)
        ON DELETE CASCADE,

    INDEX idx_comments_image_id (image_id),
    INDEX idx_comments_created_at (created_at)
);