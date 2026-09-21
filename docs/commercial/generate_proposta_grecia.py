#!/usr/bin/env python3
"""Generate Servio Greece sales proposal PDF — aggressive, client-facing."""

from __future__ import annotations

from datetime import date
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    HRFlowable,
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

OUTPUT_IT = Path(__file__).resolve().parent / "Servio_Proposta_Commerciale_Grecia.pdf"
OUTPUT_EL = Path(__file__).resolve().parent / "Servio_Proposta_Commerciale_Grecia_EL.pdf"
FONT_DIR = Path("C:/Windows/Fonts")

EMAIL = "csarannabiagio@gmail.com"
PHONE = "694 346 4283"
WHATSAPP = "+39 368 804 6394"
CONTACTS_LINE = f"{EMAIL} · {PHONE} · WhatsApp {WHATSAPP}"

MARGIN = 2.0 * cm
PAGE_WIDTH, _PAGE_HEIGHT = A4
USABLE_WIDTH = PAGE_WIDTH - 2 * MARGIN
FOOTER_H = 1.0 * cm
TEAL = colors.HexColor("#0F766E")
TEAL_DARK = colors.HexColor("#115E59")
INK = colors.HexColor("#0F172A")
MUTED = colors.HexColor("#475569")
RED = colors.HexColor("#B91C1C")


def register_fonts() -> tuple[str, str]:
    regular = str(FONT_DIR / "segoeui.ttf")
    bold = str(FONT_DIR / "segoeuib.ttf")
    pdfmetrics.registerFont(TTFont("SegoeUI", regular))
    pdfmetrics.registerFont(TTFont("SegoeUIBold", bold))
    return "SegoeUI", "SegoeUIBold"


def build_styles(regular: str, bold: str) -> dict:
    base = getSampleStyleSheet()
    return {
        "title": ParagraphStyle(
            "title",
            parent=base["Normal"],
            fontName=bold,
            fontSize=28,
            leading=34,
            textColor=TEAL,
            spaceAfter=10,
            alignment=TA_CENTER,
        ),
        "cover_punch": ParagraphStyle(
            "cover_punch",
            parent=base["Normal"],
            fontName=bold,
            fontSize=16,
            leading=22,
            textColor=INK,
            alignment=TA_CENTER,
            spaceAfter=14,
        ),
        "subtitle": ParagraphStyle(
            "subtitle",
            parent=base["Normal"],
            fontName=regular,
            fontSize=11,
            leading=16,
            textColor=MUTED,
            alignment=TA_CENTER,
            spaceAfter=8,
        ),
        "h1": ParagraphStyle(
            "h1",
            parent=base["Normal"],
            fontName=bold,
            fontSize=15,
            leading=19,
            textColor=TEAL,
            spaceBefore=12,
            spaceAfter=8,
            keepWithNext=True,
        ),
        "h2": ParagraphStyle(
            "h2",
            parent=base["Normal"],
            fontName=bold,
            fontSize=11,
            leading=14,
            textColor=TEAL_DARK,
            spaceBefore=10,
            spaceAfter=6,
            keepWithNext=True,
        ),
        "body": ParagraphStyle(
            "body",
            parent=base["Normal"],
            fontName=regular,
            fontSize=10,
            leading=15,
            alignment=TA_JUSTIFY,
            spaceAfter=8,
            textColor=INK,
        ),
        "punch": ParagraphStyle(
            "punch",
            parent=base["Normal"],
            fontName=bold,
            fontSize=12,
            leading=16,
            alignment=TA_LEFT,
            textColor=RED,
            spaceBefore=4,
            spaceAfter=10,
        ),
        "bullet": ParagraphStyle(
            "bullet",
            parent=base["Normal"],
            fontName=regular,
            fontSize=10,
            leading=15,
            leftIndent=12,
            spaceAfter=5,
            textColor=INK,
        ),
        "footer": ParagraphStyle(
            "footer",
            parent=base["Normal"],
            fontName=regular,
            fontSize=8.5,
            leading=12,
            textColor=colors.HexColor("#64748B"),
            alignment=TA_CENTER,
        ),
        "cover_meta": ParagraphStyle(
            "cover_meta",
            parent=base["Normal"],
            fontName=regular,
            fontSize=10,
            leading=14,
            alignment=TA_CENTER,
            textColor=MUTED,
        ),
        "th": ParagraphStyle(
            "th",
            parent=base["Normal"],
            fontName=bold,
            fontSize=8.5,
            leading=11,
            textColor=colors.white,
            alignment=TA_LEFT,
            wordWrap="CJK",
        ),
        "td": ParagraphStyle(
            "td",
            parent=base["Normal"],
            fontName=regular,
            fontSize=8.5,
            leading=12,
            alignment=TA_LEFT,
            wordWrap="CJK",
        ),
        "td_bold": ParagraphStyle(
            "td_bold",
            parent=base["Normal"],
            fontName=bold,
            fontSize=8.5,
            leading=12,
            alignment=TA_LEFT,
            wordWrap="CJK",
        ),
    }


def bullet(text: str, styles: dict) -> Paragraph:
    return Paragraph(f"&bull; {text}", styles["bullet"])


def col_widths(ratios: list[float]) -> list[float]:
    total = sum(ratios)
    return [USABLE_WIDTH * (r / total) for r in ratios]


def make_table(
    rows: list[list[str]],
    ratios: list[float],
    styles: dict,
    bold_first: bool = False,
) -> Table:
    header = rows[0]
    data = [[Paragraph(str(cell), styles["th"]) for cell in header]]
    for row in rows[1:]:
        cells = []
        for i, cell in enumerate(row):
            style = styles["td_bold"] if (bold_first and i == 0) else styles["td"]
            cells.append(Paragraph(str(cell), style))
        data.append(cells)

    t = Table(data, colWidths=col_widths(ratios), hAlign="LEFT", repeatRows=1, splitByRow=1)
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), TEAL),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#CBD5E1")),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F8FAFC")]),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 8),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        )
    )
    return t


def money_box(styles: dict, html: str) -> Table:
    inner = Paragraph(
        html,
        ParagraphStyle(
            "money",
            parent=styles["body"],
            fontName="SegoeUI",
            fontSize=10,
            leading=15,
            textColor=colors.HexColor("#7F1D1D"),
            alignment=TA_LEFT,
            spaceAfter=0,
        ),
    )
    t = Table([[inner]], colWidths=[USABLE_WIDTH])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#FEF2F2")),
                ("BOX", (0, 0), (-1, -1), 1.2, RED),
                ("LEFTPADDING", (0, 0), (-1, -1), 12),
                ("RIGHTPADDING", (0, 0), (-1, -1), 12),
                ("TOPPADDING", (0, 0), (-1, -1), 12),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
            ]
        )
    )
    return t


def add_page_number(canvas, doc, label: str):
    canvas.saveState()
    canvas.setFont("SegoeUI", 9)
    canvas.setFillColor(colors.HexColor("#64748B"))
    canvas.drawCentredString(PAGE_WIDTH / 2, 1.2 * cm, f"{label} {doc.page}")
    canvas.restoreState()


def page_fn_it(canvas, doc):
    add_page_number(canvas, doc, "Servio · Offerta riservata · pagina")


def page_fn_el(canvas, doc):
    add_page_number(canvas, doc, "Servio · Εμπιστευτική προσφορά · σελίδα")


def build_story(styles: dict) -> list:
    today = date.today().strftime("%d/%m/%Y")
    story: list = []

    # Cover
    story.append(Spacer(1, 2.6 * cm))
    story.append(Paragraph("SERVIO", styles["title"]))
    story.append(
        Paragraph(
            "Ogni sera perdi soldi.<br/>Non perché cucini male.<br/>Perché i tavoli aspettano.",
            styles["cover_punch"],
        )
    )
    story.append(Spacer(1, 0.4 * cm))
    story.append(
        Paragraph(
            "Proposta commerciale per il tuo ristorante, taverna o bar in Grecia.<br/>"
            "QR al tavolo. Ordine diretto. Cucina live. Zero commissioni sugli ordini in sala.",
            styles["subtitle"],
        )
    )
    story.append(Spacer(1, 1.4 * cm))
    story.append(
        Paragraph(
            f"Documento per il titolare · {today}<br/>"
            "Da leggere in 6 minuti. Poi decidi.<br/>"
            f"{CONTACTS_LINE}",
            styles["cover_meta"],
        )
    )
    story.append(PageBreak())

    # 1. The no is already costing you
    story.append(Paragraph("1. Il tuo «no» è già in cassa, in negativo", styles["h1"]))
    story.append(
        Paragraph(
            "Non ti stiamo vendendo un'app. Ti stiamo mostrando i soldi che escono "
            "dal locale mentre i clienti hanno il telefono in mano e il cameriere è al tavolo 12.",
            styles["body"],
        )
    )
    story.append(
        Paragraph(
            "In Grecia il food &amp; beverage vale circa €9,5 miliardi. "
            "Il fatturato 2025 è sceso del 3,4%. I costi del personale no. "
            "efood e Wolt si prendono fino al 27% di ogni ordine. "
            "Tu lavori. Loro incassano.",
            styles["body"],
        )
    )
    story.append(Paragraph("Cosa succede stasera, senza Servio", styles["h2"]))
    for item in [
        "Il turista siede, apre Google Translate, aspetta. 8-12 minuti. Ha già deciso di non prendere il secondo giro.",
        "Il cameriere prende l'ordine a voce. Un extra sbagliato, un allergene perso, un piatto rifatto. Quello è margine bruciato.",
        "In peak hour i tavoli non girano. Una coperta persa a sera, per 90 sere, è un mese di affitto.",
        "Il menu di carta è in greco. Il tedesco e l'italiano ordinano «qualcosa di sicuro». Ticket medio più basso.",
        "Chiami efood per il takeaway e gli dai un quarto del piatto. Perché non hai un canale tuo.",
    ]:
        story.append(bullet(item, styles))

    story.append(Spacer(1, 8))
    story.append(
        money_box(
            styles,
            "<b>Un locale da 40 tavoli in alta stagione.</b><br/><br/>"
            "Se anche solo 20 tavoli prendono <b>un giro di bevande in più</b> "
            "perché non aspettano il cameriere: 20 × €6 = <b>€120 a sera</b>.<br/><br/>"
            "90 sere di stagione = <b>€10.800</b>.<br/>"
            "Servio costa <b>€299 all'anno + IVA</b>.<br/><br/>"
            "<b>Il no ti costa più del sì. Ogni settimana.</b>",
        )
    )
    story.append(PageBreak())

    # 2. Objections
    story.append(Paragraph("2. Tutti i tuoi «no». Li smontiamo uno a uno.", styles["h1"]))
    story.append(
        Paragraph(
            "Se stai per chiudere questo PDF, è per uno di questi motivi. "
            "Sono tutti sbagliati. Ecco perché.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Il tuo no", "La realtà", "Cosa fai con Servio"],
                [
                    "Ho già i camerieri",
                    "I camerieri non spariscono. Restano bloccati a prendere comande mentre i drink e i dessert non escono.",
                    "Il cliente ordina. Il cameriere serve. Il tavolo spende di più.",
                ],
                [
                    "I clienti non lo useranno",
                    "Il 90% dei turisti ha già ordinato da un QR. Atene, Mykonos, Creta: è lo standard, non il futuro.",
                    "4 lingue sul telefono. Zero app da scaricare. Si ordina in 30 secondi.",
                ],
                [
                    "Costa troppo",
                    "€299 all'anno + IVA. Meno di 1 euro al giorno. Meno di un cameriere per una sera.",
                    "Un prezzo. Un anno. Niente commissioni sugli ordini in sala.",
                ],
                [
                    "Ho già il POS / myDATA",
                    "Perfetto. Non te lo sostituiamo. Cambiare cassa è un incubo fiscale. Lo sappiamo.",
                    "Servio si innesta. SoftOne, Epsilon PYLON, o il tuo POS attuale.",
                ],
                [
                    "Aspetto dopo la stagione",
                    "La stagione è quando perdi di più. Installare a ottobre è comodo. Guadagnare è adesso.",
                    "Go-live in giorni, non mesi. Menu, QR, training: fatto.",
                ],
                [
                    "C'è già Quickord / Ordio",
                    "Loro vogliono essere la tua cassa. Tu hai già una cassa. Non ti serve un altro software da rifare da zero.",
                    "Noi siamo il pezzo che manca: QR, cucina live, chiamata cameriere. Punto.",
                ],
            ],
            [1.3, 2.2, 2.0],
            styles,
            bold_first=True,
        )
    )
    story.append(Spacer(1, 10))
    story.append(
        Paragraph(
            "Se dopo questa tabella il no resta un no, non è strategia. È abitudine. "
            "L'abitudine, in alta stagione, costa caro.",
            styles["punch"],
        )
    )
    story.append(PageBreak())

    # 3. What you get tonight
    story.append(Paragraph("3. Cosa succede il giorno in cui dici sì", styles["h1"]))
    story.append(
        Paragraph(
            "Il cliente scansiona il QR del tavolo. Vede il menu in greco, inglese, italiano o tedesco. "
            "Ordina. Paga, se vuoi. Chiama il cameriere se serve. "
            "La cucina vede l'ordine in tempo reale. Lo scontrino medio sale. I tavoli girano.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Oggi", "Con Servio"],
                ["Attesa cameriere 8-12 min", "Ordine in 30 secondi dal telefono"],
                ["Errori di comanda 5-8%", "Il cliente scrive. Errori giù del 60%"],
                [                    "Menu carta, una lingua", "IT, EN, EL, DE. Prezzi aggiornati al volo"],
                ["Cameriere prende e serve", "Cameriere serve. Più tavoli, stesso staff"],
                ["efood 27% a ordine", "In sala: zero commissioni. €299/anno + IVA, punto"],
                ["Cassa già certificata AADE", "La tieni. Servio si collega, non la butta"],
            ],
            [1.0, 1.0],
            styles,
        )
    )
    story.append(Spacer(1, 10))
    story.append(Paragraph("Perché tu, e non il software del vicino", styles["h2"]))
    for item in [
        "<b>Non ti sostituiamo la cassa.</b> Quickord, Ordio, Parochos vogliono tutto. Tu rischi myDATA, multe, staff da riformare. Noi no.",
        "<b>Parliamo la lingua dei tuoi clienti.</b> Greco, inglese, italiano, tedesco. Nativi. Non un plugin.",
        "<b>Cucina e sala in live.</b> Kitchen board, chiamata cameriere, stato ordine. Non un PDF col QR stampato ieri.",
        "<b>White-label se hai più locali.</b> Il tuo brand. Il nostro motore. Catene e hotel, una dashboard.",
        "<b>Paghi un flat.</b> Non una fetta di ogni piatto. Spevdo ha capito il gioco sulle consegne. Noi lo facciamo in sala.",
    ]:
        story.append(bullet(item, styles))

    story.append(PageBreak())

    # 4. Price - framed as steal
    story.append(Paragraph("4. Il prezzo. Poi dimmi ancora che «non è il momento».", styles["h1"]))
    story.append(
        Paragraph(
            "Un prezzo. Tutto l'anno. IVA esclusa. "
            "Quickord parte da €21 al mese e poi aggiunge pezzi. "
            "Ordio €27-59 e ti chiede di vivere dentro il loro POS. "
            "efood ti prende il 27% a ordine. "
            "Tu paghi <b>€299 + IVA</b> e chiudi la pratica.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Voce", "Importo"],
                ["Canone annuale Servio", "€299 + IVA"],
                ["Al giorno", "Meno di €0,82"],
                ["Commissione per ordine in sala", "€0"],
                ["Setup, menu 4 lingue, QR tavoli", "Incluso"],
                ["Kitchen board + chiamata cameriere", "Incluso"],
            ],
            [2.2, 1.8],
            styles,
            bold_first=True,
        )
    )
    story.append(Spacer(1, 10))
    story.append(Paragraph("Cosa è incluso. Senza asterischi.", styles["h2"]))
    for item in [
        "Menu QR in greco, inglese, italiano, tedesco. Prezzi aggiornati al volo.",
        "Ordini al tavolo, kitchen board live, chiamata cameriere.",
        "QR stampati fino a 40 tavoli. Training staff.",
        "Zero commissioni sugli ordini in sala. Zero costi nascosti a pezzo.",
        "Integrazione POS (SoftOne / Epsilon) se ce l'hai già.",
    ]:
        story.append(bullet(item, styles))

    story.append(Spacer(1, 8))
    story.append(
        Paragraph(
            "€299 all'anno + IVA. Meno di un cameriere per una sera. "
            "Se il secondo giro di drink di una sola notte copre l'anno, il «no» è un lusso.",
            styles["punch"],
        )
    )
    story.append(PageBreak())

    # 5. Close
    story.append(Paragraph("5. Non ti chiediamo di credere. Ti chiediamo di misurare.", styles["h1"]))
    story.append(
        Paragraph(
            "Niente roadmap da consulente. Niente «ci pensiamo a ottobre». "
            "Questa settimana accendi un locale. Tra 14 giorni guardi tre numeri: "
            "tempo di ordine, ticket medio, errori in cucina.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Quando", "Cosa facciamo", "Cosa fai tu"],
                ["Oggi", "Demo 15 minuti sul tuo menu, in greco", "Mandi il menu. Decidi."],
                ["48 ore", "QR, tavoli, staff, cucina live", "Un cameriere in training. 30 minuti."],
                ["7 giorni", "Prime comande reali in sala", "Guardi se i tavoli ordinano da soli."],
                ["30 giorni", "Report: ticket, errori, turnover", "Confronti con il mese prima."],
                ["12 mesi", "€299 + IVA. Tutto incluso.", "Usi. Misuri. Rinnovi se i numeri tornano."],
            ],
            [0.9, 2.0, 2.0],
            styles,
            bold_first=True,
        )
    )
    story.append(Spacer(1, 12))
    story.append(Paragraph("L'unica domanda che conta", styles["h2"]))
    story.append(
        Paragraph(
            "Stasera hai di nuovo 40 tavoli, tre camerieri in ritardo e un turista tedesco "
            "che non capisce il menu. Puoi lasciarlo aspettare. "
            "O mettergli il catalogo in tasca e farti pagare il secondo giro.",
            styles["body"],
        )
    )
    story.append(
        Paragraph(
            "Il software non è il punto. I soldi che escono mentre dici «non ora» lo sono.",
            styles["punch"],
        )
    )
    story.append(Spacer(1, 8))
    story.append(Paragraph("Prossimo passo. Uno solo.", styles["h2"]))
    for item in [
        "Rispondi a questo documento con: <b>SÌ, partiamo</b>.",
        "Ti chiediamo: nome locale, numero tavoli, lingua principale dei clienti.",
        "Entro 24 ore hai la demo sul tuo menu. Non su un catalogo finto.",
        "Se dopo la demo è ancora no, chiudiamo. Se è sì, sei live prima del weekend.",
        f"Email: <b>{EMAIL}</b> · Tel: <b>{PHONE}</b> · WhatsApp: <b>{WHATSAPP}</b>",
    ]:
        story.append(bullet(item, styles))

    story.append(Spacer(1, 16))
    story.append(HRFlowable(width="100%", thickness=0.8, color=TEAL))
    story.append(Spacer(1, 10))
    story.append(
        Paragraph(
            "<b>Servio</b> · Ordina dal tavolo. Senza attesa.<br/>"
            f"{EMAIL} · {PHONE} · WhatsApp {WHATSAPP}<br/>"
            "Canone: €299 / anno + IVA · Zero commissioni in sala",
            styles["footer"],
        )
    )
    return story


def build_story_el(styles: dict) -> list:
    today = date.today().strftime("%d/%m/%Y")
    story: list = []

    story.append(Spacer(1, 2.6 * cm))
    story.append(Paragraph("SERVIO", styles["title"]))
    story.append(
        Paragraph(
            "Κάθε βράδυ χάνεις λεφτά.<br/>Όχι επειδή μαγειρεύεις άσχημα.<br/>Επειδή τα τραπέζια περιμένουν.",
            styles["cover_punch"],
        )
    )
    story.append(Spacer(1, 0.4 * cm))
    story.append(
        Paragraph(
            "Εμπορική πρόταση για το εστιατόριο, την ταβέρνα ή το bar σου στην Ελλάδα.<br/>"
            "QR στο τραπέζι. Παραγγελία απευθείας. Κουζίνα live. Μηδέν προμήθεια στη σάλα.",
            styles["subtitle"],
        )
    )
    story.append(Spacer(1, 1.4 * cm))
    story.append(
        Paragraph(
            f"Έγγραφο για τον ιδιοκτήτη · {today}<br/>"
            "Το διαβάζεις σε 6 λεπτά. Μετά αποφασίζεις.<br/>"
            f"{CONTACTS_LINE}",
            styles["cover_meta"],
        )
    )
    story.append(PageBreak())

    story.append(Paragraph("1. Το «όχι» σου είναι ήδη στο ταμείο, με μείον", styles["h1"]))
    story.append(
        Paragraph(
            "Δεν σου πουλάμε εφαρμογή. Σου δείχνουμε τα λεφτά που φεύγουν από το μαγαζί "
            "όσο οι πελάτες έχουν το κινητό στο χέρι και ο σερβιτόρος είναι στο τραπέζι 12.",
            styles["body"],
        )
    )
    story.append(
        Paragraph(
            "Στην Ελλάδα το food &amp; beverage αξίζει περίπου €9,5 δισ. "
            "Ο τζίρος του 2025 έπεσε 3,4%. Το κόστος προσωπικού όχι. "
            "Το efood και η Wolt παίρνουν έως 27% από κάθε παραγγελία. "
            "Εσύ δουλεύεις. Αυτοί εισπράττουν.",
            styles["body"],
        )
    )
    story.append(Paragraph("Τι γίνεται απόψε, χωρίς Servio", styles["h2"]))
    for item in [
        "Ο τουρίστας κάθεται, ανοίγει Google Translate, περιμένει. 8-12 λεπτά. Έχει ήδη αποφασίσει να μην πάρει δεύτερο γύρο.",
        "Ο σερβιτόρος παίρνει την παραγγελία φωναχτά. Λάθος extra, αλλεργιογόνο που χάθηκε, πιάτο ξανά. Αυτό είναι περιθώριο που καίγεται.",
        "Στην αιχμή τα τραπέζια δεν γυρίζουν. Ένα χαμένο κάλυμμα το βράδυ, για 90 βράδια, είναι ένας μήνας ενοίκιο.",
        "Το χάρτινο μενού είναι στα ελληνικά. Ο Γερμανός και ο Ιταλός παραγγέλνουν «κάτι σίγουρο». Μέσος λογαριασμός πιο χαμηλά.",
        "Καλείς το efood για takeaway και του δίνεις το ένα τέταρτο του πιάτου. Γιατί δεν έχεις δικό σου κανάλι.",
    ]:
        story.append(bullet(item, styles))

    story.append(Spacer(1, 8))
    story.append(
        money_box(
            styles,
            "<b>Κατάστημα 40 τραπεζιών στην υψηλή σεζόν.</b><br/><br/>"
            "Αν μόνο 20 τραπέζια πάρουν <b>έναν επιπλέον γύρο ποτών</b> "
            "επειδή δεν περιμένουν τον σερβιτόρο: 20 × €6 = <b>€120 το βράδυ</b>.<br/><br/>"
            "90 βράδια σεζόν = <b>€10.800</b>.<br/>"
            "Το Servio κοστίζει <b>€299 τον χρόνο + ΦΠΑ</b>.<br/><br/>"
            "<b>Το όχι σου κοστίζει περισσότερο από το ναι. Κάθε εβδομάδα.</b>",
        )
    )
    story.append(PageBreak())

    story.append(Paragraph("2. Όλα τα «όχι» σου. Τα σπάμε ένα ένα.", styles["h1"]))
    story.append(
        Paragraph(
            "Αν κλείνεις αυτό το PDF, είναι για έναν από αυτούς τους λόγους. "
            "Είναι όλοι λάθος. Να γιατί.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Το όχι σου", "Η πραγματικότητα", "Τι κάνεις με το Servio"],
                [
                    "Έχω ήδη σερβιτόρους",
                    "Οι σερβιτόροι δεν εξαφανίζονται. Μένουν κολλημένοι στις παραγγελίες ενώ τα ποτά και τα γλυκά δεν βγαίνουν.",
                    "Ο πελάτης παραγγέλνει. Ο σερβιτόρος σερβίρει. Το τραπέζι ξοδεύει περισσότερα.",
                ],
                [
                    "Οι πελάτες δεν θα το χρησιμοποιήσουν",
                    "Το 90% των τουριστών έχει ήδη παραγγείλει από QR. Αθήνα, Μύκονος, Κρήτη: είναι το στάνταρ, όχι το μέλλον.",
                    "4 γλώσσες στο κινητό. Καμία εφαρμογή για κατέβασμα. Παραγγελία σε 30 δευτερόλεπτα.",
                ],
                [
                    "Κοστίζει πολύ",
                    "€299 τον χρόνο + ΦΠΑ. Κάτω από 1 ευρώ τη μέρα. Λιγότερο από έναν σερβιτόρο για ένα βράδυ.",
                    "Μία τιμή. Ένας χρόνος. Καμία προμήθεια στις παραγγελίες της σάλας.",
                ],
                [
                    "Έχω ήδη POS / myDATA",
                    "Τέλεια. Δεν το αλλάζουμε. Η αλλαγή ταμειακής είναι φορολογικός εφιάλτης. Το ξέρουμε.",
                    "Το Servio δένει πάνω. SoftOne, Epsilon PYLON, ή το POS που έχεις ήδη.",
                ],
                [
                    "Περιμένω μετά τη σεζόν",
                    "Η σεζόν είναι όταν χάνεις τα περισσότερα. Εγκατάσταση τον Οκτώβριο είναι άνετη. Τα κέρδη είναι τώρα.",
                    "Go-live σε μέρες, όχι μήνες. Μενού, QR, εκπαίδευση: έτοιμα.",
                ],
                [
                    "Υπάρχει ήδη Quickord / Ordio",
                    "Αυτοί θέλουν να γίνουν η ταμειακή σου. Εσύ έχεις ήδη ταμειακή. Δεν χρειάζεσαι άλλο λογισμικό από μηδέν.",
                    "Εμείς είμαστε το κομμάτι που λείπει: QR, live κουζίνα, κλήση σερβιτόρου. Τέλος.",
                ],
            ],
            [1.3, 2.2, 2.0],
            styles,
            bold_first=True,
        )
    )
    story.append(Spacer(1, 10))
    story.append(
        Paragraph(
            "Αν μετά από αυτόν τον πίνακα το όχι μείνει όχι, δεν είναι στρατηγική. Είναι συνήθεια. "
            "Η συνήθεια, στην υψηλή σεζόν, κοστίζει ακριβά.",
            styles["punch"],
        )
    )
    story.append(PageBreak())

    story.append(Paragraph("3. Τι γίνεται τη μέρα που λες ναι", styles["h1"]))
    story.append(
        Paragraph(
            "Ο πελάτης σκανάρει το QR του τραπεζιού. Βλέπει το μενού στα ελληνικά, αγγλικά, ιταλικά ή γερμανικά. "
            "Παραγγέλνει. Πληρώνει, αν θέλεις. Καλεί τον σερβιτόρο αν χρειάζεται. "
            "Η κουζίνα βλέπει την παραγγελία σε πραγματικό χρόνο. Ο μέσος λογαριασμός ανεβαίνει. Τα τραπέζια γυρίζουν.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Σήμερα", "Με το Servio"],
                ["Αναμονή σερβιτόρου 8-12 λεπτά", "Παραγγελία σε 30 δευτερόλεπτα από το κινητό"],
                ["Λάθη παραγγελίας 5-8%", "Γράφει ο πελάτης. Λάθη κάτω 60%"],
                ["Χάρτινο μενού, μία γλώσσα", "EL, EN, IT, DE. Τιμές live"],
                ["Ο σερβιτόρος παίρνει και σερβίρει", "Ο σερβιτόρος σερβίρει. Περισσότερα τραπέζια, ίδιο προσωπικό"],
                ["efood 27% ανά παραγγελία", "Στη σάλα: μηδέν προμήθεια. €299/έτος + ΦΠΑ"],
                ["Ταμειακή ήδη πιστοποιημένη ΑΑΔΕ", "Την κρατάς. Το Servio συνδέεται, δεν την πετάει"],
            ],
            [1.0, 1.0],
            styles,
        )
    )
    story.append(Spacer(1, 10))
    story.append(Paragraph("Γιατί εσύ, και όχι το λογισμικό του διπλανού", styles["h2"]))
    for item in [
        "<b>Δεν αλλάζουμε την ταμειακή σου.</b> Quickord, Ordio, Parochos θέλουν τα πάντα. Εσύ ρισκάρεις myDATA, πρόστιμα, προσωπικό από την αρχή. Εμείς όχι.",
        "<b>Μιλάμε τη γλώσσα των πελατών σου.</b> Ελληνικά, αγγλικά, ιταλικά, γερμανικά. Native. Όχι plugin.",
        "<b>Κουζίνα και σάλα live.</b> Kitchen board, κλήση σερβιτόρου, κατάσταση παραγγελίας. Όχι PDF με QR τυπωμένο χθες.",
        "<b>White-label αν έχεις πολλά καταστήματα.</b> Το brand σου. Η μηχανή μας. Αλυσίδες και ξενοδοχεία, ένα dashboard.",
        "<b>Πληρώνεις flat.</b> Όχι κομμάτι από κάθε πιάτο. Στη σάλα το κόστος είναι σταθερό.",
    ]:
        story.append(bullet(item, styles))

    story.append(PageBreak())

    story.append(Paragraph("4. Η τιμή. Πες μου ακόμα ότι «δεν είναι η στιγμή».", styles["h1"]))
    story.append(
        Paragraph(
            "Μία τιμή. Όλο τον χρόνο. Χωρίς ΦΠΑ. "
            "Το Quickord ξεκινά από €21 τον μήνα και μετά προσθέτει κομμάτια. "
            "Το Ordio €27-59 και σου ζητά να ζήσεις μέσα στο POS τους. "
            "Το efood παίρνει 27% ανά παραγγελία. "
            "Εσύ πληρώνεις <b>€299 + ΦΠΑ</b> και τελειώνει η κουβέντα.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Στοιχείο", "Ποσό"],
                ["Ετήσιο πάγιο Servio", "€299 + ΦΠΑ"],
                ["Ανά ημέρα", "Κάτω από €0,82"],
                ["Προμήθεια ανά παραγγελία στη σάλα", "€0"],
                ["Setup, μενού 4 γλωσσών, QR τραπεζιών", "Συμπεριλαμβάνεται"],
                ["Kitchen board + κλήση σερβιτόρου", "Συμπεριλαμβάνεται"],
            ],
            [2.2, 1.8],
            styles,
            bold_first=True,
        )
    )
    story.append(Spacer(1, 10))
    story.append(Paragraph("Τι περιλαμβάνεται. Χωρίς αστερίσκους.", styles["h2"]))
    for item in [
        "Μενού QR στα ελληνικά, αγγλικά, ιταλικά, γερμανικά. Τιμές που αλλάζουν αμέσως.",
        "Παραγγελίες στο τραπέζι, kitchen board live, κλήση σερβιτόρου.",
        "Τυπωμένα QR έως 40 τραπέζια. Εκπαίδευση προσωπικού.",
        "Μηδέν προμήθεια στις παραγγελίες της σάλας. Μηδέν κρυφά κόστη ανά κομμάτι.",
        "Σύνδεση POS (SoftOne / Epsilon) αν το έχεις ήδη.",
    ]:
        story.append(bullet(item, styles))

    story.append(Spacer(1, 8))
    story.append(
        Paragraph(
            "€299 τον χρόνο + ΦΠΑ. Λιγότερο από έναν σερβιτόρο για ένα βράδυ. "
            "Αν ο δεύτερος γύρος ποτών μιας νύχτας καλύπτει τον χρόνο, το «όχι» είναι πολυτέλεια.",
            styles["punch"],
        )
    )
    story.append(PageBreak())

    story.append(Paragraph("5. Δεν σου ζητάμε να πιστέψεις. Σου ζητάμε να μετρήσεις.", styles["h1"]))
    story.append(
        Paragraph(
            "Καθόλου roadmap συμβούλου. Καθόλου «το βλέπουμε τον Οκτώβριο». "
            "Αυτή την εβδομάδα ανοίγεις ένα κατάστημα. Σε 14 μέρες κοιτάς τρεις αριθμούς: "
            "χρόνος παραγγελίας, μέσος λογαριασμός, λάθη στην κουζίνα.",
            styles["body"],
        )
    )
    story.append(
        make_table(
            [
                ["Πότε", "Τι κάνουμε εμείς", "Τι κάνεις εσύ"],
                ["Σήμερα", "Demo 15 λεπτών στο δικό σου μενού, στα ελληνικά", "Στέλνεις το μενού. Αποφασίζεις."],
                ["48 ώρες", "QR, τραπέζια, προσωπικό, live κουζίνα", "Ένας σερβιτόρος σε εκπαίδευση. 30 λεπτά."],
                ["7 ημέρες", "Πρώτες πραγματικές παραγγελίες στη σάλα", "Βλέπεις αν τα τραπέζια παραγγέλνουν μόνα τους."],
                ["30 ημέρες", "Report: λογαριασμός, λάθη, turnover", "Συγκρίνεις με τον προηγούμενο μήνα."],
                ["12 μήνες", "€299 + ΦΠΑ. Όλα μέσα.", "Χρησιμοποιείς. Μετράς. Ανανεώνεις αν βγαίνουν οι αριθμοί."],
            ],
            [0.9, 2.0, 2.0],
            styles,
            bold_first=True,
        )
    )
    story.append(Spacer(1, 12))
    story.append(Paragraph("Η μόνη ερώτηση που μετράει", styles["h2"]))
    story.append(
        Paragraph(
            "Απόψε έχεις πάλι 40 τραπέζια, τρεις σερβιτόρους αργοπορημένους και έναν Γερμανό τουρίστα "
            "που δεν καταλαβαίνει το μενού. Μπορείς να τον αφήσεις να περιμένει. "
            "Ή να του βάλεις τον κατάλογο στην τσέπη και να πληρωθείς τον δεύτερο γύρο.",
            styles["body"],
        )
    )
    story.append(
        Paragraph(
            "Το λογισμικό δεν είναι το θέμα. Τα λεφτά που φεύγουν όσο λες «όχι τώρα» είναι.",
            styles["punch"],
        )
    )
    story.append(Spacer(1, 8))
    story.append(Paragraph("Επόμενο βήμα. Ένα μόνο.", styles["h2"]))
    for item in [
        "Απάντησε σε αυτό το έγγραφο με: <b>ΝΑΙ, ξεκινάμε</b>.",
        "Ζητάμε: όνομα καταστήματος, αριθμό τραπεζιών, κύρια γλώσσα πελατών.",
        "Σε 24 ώρες έχεις demo στο δικό σου μενού. Όχι σε ψεύτικο κατάλογο.",
        "Αν μετά το demo είναι ακόμα όχι, κλείνουμε. Αν είναι ναι, είσαι live πριν το Σαββατοκύριακο.",
        f"Email: <b>{EMAIL}</b> · Τηλ: <b>{PHONE}</b> · WhatsApp: <b>{WHATSAPP}</b>",
    ]:
        story.append(bullet(item, styles))

    story.append(Spacer(1, 16))
    story.append(HRFlowable(width="100%", thickness=0.8, color=TEAL))
    story.append(Spacer(1, 10))
    story.append(
        Paragraph(
            "<b>Servio</b> · Παράγγειλε από το τραπέζι. Χωρίς ουρά.<br/>"
            f"{EMAIL} · {PHONE} · WhatsApp {WHATSAPP}<br/>"
            "Πάγιο: €299 / έτος + ΦΠΑ · Μηδέν προμήθεια στη σάλα",
            styles["footer"],
        )
    )
    return story


def write_pdf(path: Path, title: str, story_fn, page_fn) -> None:
    styles = build_styles(*register_fonts())
    doc = SimpleDocTemplate(
        str(path),
        pagesize=A4,
        rightMargin=MARGIN,
        leftMargin=MARGIN,
        topMargin=MARGIN,
        bottomMargin=MARGIN + FOOTER_H,
        title=title,
        author="Servio",
    )
    doc.build(story_fn(styles), onFirstPage=page_fn, onLaterPages=page_fn)
    print(f"PDF generato: {path}")


def main() -> None:
    write_pdf(OUTPUT_IT, "Servio - Offerta commerciale Grecia", build_story, page_fn_it)
    write_pdf(OUTPUT_EL, "Servio - Εμπορική πρόταση Ελλάδα", build_story_el, page_fn_el)


if __name__ == "__main__":
    main()
