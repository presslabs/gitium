document.addEventListener('DOMContentLoaded', function() {
    const copyButton = document.getElementById('copyButton');

    copyButton.addEventListener('click', function() {
        // Get the text to copy from the button's data attribute
        const textToCopy = copyButton.getAttribute('data-copy-text');

        // Check if navigator.clipboard is supported
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textToCopy)
                .then(() => {
                    alert('Copied to clipboard using navigator: ' + textToCopy);
                })
                .catch(err => {
                    console.error('Could not copy text with navigator: ', err);
                });
        } else {
            // Deprecated fallback for older browsers
            console.warn('Using deprecated document.execCommand("copy") as a fallback. Update your browser for better clipboard support.');
            
            // Fallback using document.execCommand
            const tempTextarea = document.createElement('textarea');
            tempTextarea.value = textToCopy;
            document.body.appendChild(tempTextarea);
            tempTextarea.select();
            
            try {
                document.execCommand('copy');
                alert('Copied to clipboard (using fallback): ' + textToCopy);
            } catch (err) {
                console.error('Fallback copy failed: ', err);
            }

            // Remove the temporary textarea
            document.body.removeChild(tempTextarea);
        }
    });
});