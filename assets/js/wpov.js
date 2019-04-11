function wpov_translate(text) {
    try {
        return wpov.translations[text]
    } catch(e) {
        return text;
    }
}