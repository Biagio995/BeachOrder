import QRCode from 'qrcode'

export async function qrDataUrl(text: string, size = 512): Promise<string> {
  return QRCode.toDataURL(text, {
    errorCorrectionLevel: 'M',
    margin: 1,
    width: size,
    color: {
      dark: '#143642',
      light: '#FFFFFF',
    },
  })
}
