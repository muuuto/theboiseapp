// Sharing a picture made on the page (WhatsApp, Instagram...). Phones open the share sheet only
// right after a tap, with nothing slow in between (iPhones are strict: even turning the picture
// into a file first is too slow), so the file is made beforehand and shared straight away.
// Where sharing files isn't possible (most computers) the picture is downloaded instead

// A data: picture (canvas.toDataURL) as a file, without waiting
export function fileFromDataUrl(dataUrl, name) {
    const [head, data] = dataUrl.split(',');
    const bytes = atob(data);
    const array = new Uint8Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) array[i] = bytes.charCodeAt(i);
    return new File([array], name, { type: head.match(/:(.*?);/)[1] });
}

export const canShareFile = (file) => typeof navigator !== 'undefined' && !!file && !!navigator.canShare?.({ files: [file] });

export function downloadFile(file) {
    const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(file), download: file.name });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}

// Call it first thing in the click handler (no await before it)
export async function shareFile(file, title) {
    if (canShareFile(file)) {
        try {
            await navigator.share({ files: [file], title });
            return;
        } catch (error) {
            // Closed the share sheet: nothing to do. Anything else: download it instead
            if (error.name === 'AbortError') return;
        }
    }
    downloadFile(file);
}
