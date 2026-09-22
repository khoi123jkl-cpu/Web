<?php
if (!headers_sent()) {
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' https://cdnjs.cloudflare.com; " .
        "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
        "img-src 'self' https: data:; " .
        "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
        "connect-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'self'"
    );
}
