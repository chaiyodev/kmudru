# Changelog - UDRU Wisdom Upgrade

All notable changes to this project will be documented in this file.

## [3.0.0] - 2026-02-17

### Added
- **AI Assistant v3.0**: 
    - Added **Conversation Memory**: The AI now remembers the last 10 messages in the session, allowing for follow-up questions (e.g., "Summarize *this*").
    - Added **Fuzzy Search**: Improved Thai keyword matching using substring fragmentation for better recall when users typo or use incomplete terms.
    - Added **Context Resolution**: Automatically detects pronouns like "เรื่องนี้", "อันนี้" and resolves them from previous search results.
    - Added **Rate Limiting**: Protection against abuse (limit of 30 messages per 5 minutes).
- **Global Edit System (`edit.php`)**: A universal editor for various content types (Document, Wiki, Q&A).
- **Activity Logging**: Extended logging for AI chat interactions.

### Security (Critical Fixes)
- **File Upload Validation (`upload.php`)**: 
    - Implemented strictly enforced extension whitelist.
    - Implemented MIME type verification.
    - Added 10MB file size limit.
    - Integrated CSRF Protection.
- **Secure Deletions**: 
    - Converted all deletion actions from vulnerable `GET` links to secure `POST` forms with CSRF tokens in:
        - `training_create.php`
        - `course_editor.php`
        - `quiz_editor.php`
- **CSRF Protection**: 
    - Added CSRF token verification to the registration process (`register.php`).
    - Added CSRF token verification to all deletion forms.
- **XSS Protection**: Ensured all user-generated content displayed by the AI assistant is properly escaped.

### Changed
- Improved AI Assistant UI with premium cards and suggestion chips.
- Enhanced profile navigation with direct edit links.
- Updated `training_create.php` and `course_editor.php` layout to support secure forms.

---
*Developed with ❤️ for UDRU Wisdom Knowledge Center*
