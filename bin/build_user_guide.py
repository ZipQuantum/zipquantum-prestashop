from pathlib import Path
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    Flowable,
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "docs" / "readme_en.pdf"
PURPLE = colors.HexColor("#5735D1")
DARK = colors.HexColor("#172033")
MUTED = colors.HexColor("#667085")
LIGHT = colors.HexColor("#F4F2FF")
GREEN = colors.HexColor("#1F7A4D")
RED = colors.HexColor("#B42318")


class UiPanel(Flowable):
    def __init__(self, title, rows, width=166 * mm):
        super().__init__()
        self.title = title
        self.rows = rows
        self.width = width
        # 11 mm header, 8 mm per row and 4 mm bottom breathing room.
        self.height = (15 + len(rows) * 8) * mm

    def draw(self):
        c = self.canv
        c.setFillColor(colors.white)
        c.setStrokeColor(colors.HexColor("#D0D5DD"))
        c.roundRect(0, 0, self.width, self.height, 6, fill=1, stroke=1)
        c.setFillColor(PURPLE)
        c.roundRect(0, self.height - 11 * mm, self.width, 11 * mm, 6, fill=1, stroke=0)
        c.rect(0, self.height - 11 * mm, self.width, 5 * mm, fill=1, stroke=0)
        c.setFillColor(colors.white)
        c.setFont("Helvetica-Bold", 11)
        c.drawString(6 * mm, self.height - 7 * mm, self.title)
        y = self.height - 17 * mm
        for label, value, state in self.rows:
            c.setFillColor(MUTED)
            c.setFont("Helvetica", 8)
            c.drawString(6 * mm, y, label)
            c.setFillColor(DARK)
            c.setFont("Helvetica-Bold", 8.5)
            c.drawString(51 * mm, y, value)
            if state:
                c.setFillColor(GREEN if state == "ok" else PURPLE)
                c.roundRect(self.width - 30 * mm, y - 3 * mm, 23 * mm, 5 * mm, 2, fill=1, stroke=0)
                c.setFillColor(colors.white)
                c.setFont("Helvetica-Bold", 6.5)
                c.drawCentredString(self.width - 18.5 * mm, y - 1.2 * mm, state.upper())
            y -= 8 * mm


def styles():
    base = getSampleStyleSheet()
    return {
        "title": ParagraphStyle(
            "Title", parent=base["Title"], fontName="Helvetica-Bold", fontSize=27,
            leading=31, textColor=DARK, alignment=TA_CENTER, spaceAfter=8 * mm
        ),
        "subtitle": ParagraphStyle(
            "Subtitle", parent=base["BodyText"], fontSize=12, leading=17,
            textColor=MUTED, alignment=TA_CENTER, spaceAfter=10 * mm
        ),
        "h1": ParagraphStyle(
            "H1", parent=base["Heading1"], fontName="Helvetica-Bold", fontSize=18,
            leading=22, textColor=DARK, spaceBefore=4 * mm, spaceAfter=4 * mm
        ),
        "h2": ParagraphStyle(
            "H2", parent=base["Heading2"], fontName="Helvetica-Bold", fontSize=12,
            leading=15, textColor=PURPLE, spaceBefore=3 * mm, spaceAfter=2 * mm
        ),
        "body": ParagraphStyle(
            "Body", parent=base["BodyText"], fontName="Helvetica", fontSize=9.5,
            leading=14, textColor=DARK, spaceAfter=2.5 * mm
        ),
        "small": ParagraphStyle(
            "Small", parent=base["BodyText"], fontName="Helvetica", fontSize=8,
            leading=11, textColor=MUTED
        ),
        "callout": ParagraphStyle(
            "Callout", parent=base["BodyText"], fontName="Helvetica-Bold", fontSize=9.5,
            leading=14, textColor=DARK, backColor=LIGHT, borderColor=PURPLE,
            borderWidth=0.7, borderPadding=8, spaceBefore=3 * mm, spaceAfter=4 * mm
        ),
    }


def bullet(text, style):
    return Paragraph("&#8226;&nbsp; " + text, style)


def numbered(number, title, text, st):
    return KeepTogether([
        Paragraph(f"{number}. {title}", st["h2"]),
        Paragraph(text, st["body"]),
    ])


def header_footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(colors.HexColor("#E4E7EC"))
    canvas.line(20 * mm, 16 * mm, 190 * mm, 16 * mm)
    canvas.setFillColor(MUTED)
    canvas.setFont("Helvetica", 7.5)
    canvas.drawString(20 * mm, 10 * mm, "ZipQuantum Smart Links & QR Codes - User Guide 1.0")
    canvas.drawRightString(190 * mm, 10 * mm, f"Page {doc.page}")
    canvas.restoreState()


def build():
    st = styles()
    doc = SimpleDocTemplate(
        str(OUTPUT), pagesize=A4, rightMargin=20 * mm, leftMargin=20 * mm,
        topMargin=18 * mm, bottomMargin=22 * mm,
        title="ZipQuantum Smart Links & QR Codes - PrestaShop User Guide",
        author="Xaere",
        subject="Installation, configuration, features, privacy and troubleshooting",
    )
    story = []

    story += [Spacer(1, 20 * mm), Paragraph("ZIPQUANTUM", st["h2"]),
              Paragraph("Smart Links & QR Codes", st["title"]),
              Paragraph("PrestaShop module 1.0 - Merchant user guide", st["subtitle"]),
              UiPanel("What this module does", [
                  ("Commerce objects", "Products, categories, promotions", "ok"),
                  ("Link modes", "Managed or attached read-only", "ok"),
                  ("Operations", "QR, clicks, bulk queue, secured cron", "ok"),
                  ("Privacy", "No tracking or fingerprinting", "ok"),
                  ("Deletion", "Remote Smart Links are always kept", "ok"),
              ]), Spacer(1, 10 * mm),
              Paragraph("Compatibility: PrestaShop 8.1 to 9.x; PHP 8.1 to 8.5; cURL, JSON and OpenSSL required.", st["callout"]),
              PageBreak()]

    story += [Paragraph("1. Installation", st["h1"]),
              Paragraph("Before installing, back up the store and test the archive on a staging copy. The module supports one independent ZipQuantum connection per shop in a multistore installation.", st["body"]),
              numbered(1, "Download the archive", "Use the Marketplace archive named zipquantum.zip. Do not rename the folder inside the archive.", st),
              numbered(2, "Open Module Manager", "In the PrestaShop back office, open Modules > Module Manager, select Install a module, and upload the ZIP.", st),
              numbered(3, "Complete installation", "Select Configure after installation. The module creates only its own two database tables and a hidden secure back-office controller.", st),
              numbered(4, "Check prerequisites", "If installation stops, verify PHP 8.1 or newer and the cURL and OpenSSL extensions. No external library is downloaded after installation.", st),
              UiPanel("Expected installation result", [
                  ("Module", "zipquantum 1.0.0", "ok"),
                  ("Database", "zqps_association and zqps_queue", "ok"),
                  ("Overrides", "None", "ok"),
                  ("Front-office assets", "None", "ok"),
              ]), PageBreak()]

    story += [Paragraph("2. Connect or create a ZipQuantum account", st["h1"]),
              Paragraph("The module is an OAuth public client. It never contains a client secret. A one-time PKCE verifier stays encrypted in the store while the browser window handles sign-in or Free account creation.", st["body"]),
              numbered(1, "Start", "Select Connect or create a ZipQuantum account in section 1.", st),
              numbered(2, "Authorise", "A new secure ZipQuantum window opens. Sign in, create an account if needed, review the requested integration access, and approve.", st),
              numbered(3, "Return", "Leave the PrestaShop page open. It polls the one-time handoff and reloads when the account is connected.", st),
              numbered(4, "Confirm context", "The connected account name appears. Select a managed subdomain or a verified custom domain in section 2.", st),
              UiPanel("Account connection", [
                  ("Connection", "Connected", "ok"),
                  ("Account", "Merchant account", "ok"),
                  ("Plan", "Free, Starter or Pro", "ok"),
                  ("Tokens", "Encrypted and rotating", "ok"),
              ]),
              Paragraph("If a database copy changes the shop origin or path, synchronization stops. Use Move existing installation only for the same real shop. Use Create a new installation for an independent clone; existing local associations are quarantined.", st["callout"]),
              PageBreak()]

    story += [Paragraph("3. Routing and automation", st["h1"]),
              Paragraph("A verified custom domain takes precedence over a managed zq.tn subdomain. Configure one routing choice before bulk creation.", st["body"]),
              UiPanel("Routing and automatic synchronization", [
                  ("Managed subdomain", "merchant", "ok"),
                  ("Verified domain", "Optional", ""),
                  ("Promotion path", "/order", "ok"),
                  ("Auto-create", "Explicit opt-in", "ok"),
                  ("Selected objects", "Product, category, promotion", "ok"),
              ]),
              Paragraph("Managed fields", st["h2"]),
              bullet("Destination URL: the canonical storefront URL generated by PrestaShop.", st["body"]),
              bullet("Preview title and description: localized store content, truncated safely.", st["body"]),
              bullet("Preview image: product cover or category image when one exists.", st["body"]),
              Paragraph("Automatic creation is disabled until the merchant selects it. After opt-in, additions and updates enqueue work; they do not block the catalogue save request.", st["callout"]),
              PageBreak()]

    story += [Paragraph("4. Create, attach and use QR codes", st["h1"]),
              Paragraph("Use the PrestaShop object ID visible in its back-office URL or list. The three 1.0 object types are product, category, and promotion/coupon.", st["body"]),
              numbered(1, "Managed link", "Choose the object type and ID, then select Create or synchronize managed link. The queue sends the four managed fields to ZipQuantum.", st),
              numbered(2, "Attached link", "Enter a Smart Link ID owned by the connected account, then select Attach existing link. Attached associations are read-only.", st),
              numbered(3, "QR", "After synchronization, select Download in the QR column. QR rendering is provided by ZipQuantum and contains the Smart Link URL.", st),
              numbered(4, "Clicks", "Select Refresh click totals to update the simple aggregate displayed in the table.", st),
              UiPanel("Smart Links and simple analytics", [
                  ("product #42", "https://brand.zq.tn/item-42", "ok"),
                  ("Mode", "managed", "ok"),
                  ("Clicks", "128", "ok"),
                  ("QR", "Download", "ok"),
              ]),
              Paragraph("The module does not collect click events. It only reads the aggregate total already maintained by ZipQuantum.", st["callout"]),
              PageBreak()]

    story += [Paragraph("5. Bulk queue and cron", st["h1"]),
              Paragraph("Bulk enqueue adds up to 500 active objects of one selected type. Progress is durable and can continue in later requests.", st["body"]),
              UiPanel("Queue controls", [
                  ("Pending", "16", ""),
                  ("Retry", "2", ""),
                  ("Failed", "0", "ok"),
                  ("Process", "Next batch", "ok"),
                  ("Cron", "Unique per-shop token", "ok"),
              ]),
              Paragraph("Queue outcomes", st["h2"]),
              bullet("Network and service errors retry with increasing delays and jitter.", st["body"]),
              bullet("Rate limits honour the service Retry-After value.", st["body"]),
              bullet("Invalid object data fails explicitly and can be retried after correction.", st["body"]),
              bullet("Expired credentials block the row until reconnect and Resume blocked.", st["body"]),
              bullet("Identity mismatch blocks the shop queue until the merchant resolves the move or clone.", st["body"]),
              Paragraph("Copy the secured cron URL only into a scheduler you control. Treat its token as a password. Regenerate the module installation if the URL is exposed.", st["callout"]),
              PageBreak()]

    story += [Paragraph("6. Promotions, privacy and deletion", st["h1"]),
              Paragraph("A code-based cart rule uses a shareable module URL. Opening it validates that the promotion exists and is active, creates a cart if needed, applies the code, and redirects only to a local path. Automatic cart rules link directly to that path.", st["body"]),
              Paragraph("Data sent to ZipQuantum", st["h2"]),
              bullet("Connected shop origin and path, local installation UUID, and security handoff values.", st["body"]),
              bullet("Selected object's public URL, title, description, image URL, type, and local ID.", st["body"]),
              Paragraph("Data not sent", st["h2"]),
              bullet("Customers, carts, orders, email addresses, IP addresses, visitor events, fingerprints, advertising identifiers, or device identifiers.", st["body"]),
              Paragraph("Deletion rule", st["h2"]),
              Paragraph("Deleting a product, category, or promotion removes only the local association and cancels local pending work. Disconnecting or uninstalling behaves the same way. Remote Smart Links remain in the merchant's ZipQuantum account until the merchant explicitly manages them there.", st["callout"]),
              PageBreak()]

    troubleshooting = [
        ["Symptom", "Action"],
        ["Popup does not open", "Allow pop-ups for the back office and start the connection again."],
        ["Reconnect required", "Reconnect the account, then select Resume blocked."],
        ["Identity mismatch", "Choose Move for the same shop or Create a new installation for a clone."],
        ["Queue shows retry", "Wait for the due time or check network access to the ZipQuantum API."],
        ["Queue shows failed", "Correct the object, routing domain, or account limit, then select Retry failed."],
        ["Coupon does not apply", "Confirm the cart rule is active, code-based, in date, and valid for the cart."],
        ["No QR", "Process the queue successfully; QR is returned by ZipQuantum after sync."],
    ]
    table = Table(troubleshooting, colWidths=[45 * mm, 118 * mm], repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), PURPLE),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTNAME", (0, 1), (-1, -1), "Helvetica"),
        ("FONTSIZE", (0, 0), (-1, -1), 8),
        ("LEADING", (0, 0), (-1, -1), 11),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F8F9FB")]),
        ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#D0D5DD")),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    story += [Paragraph("7. Troubleshooting", st["h1"]), table, Spacer(1, 5 * mm),
              Paragraph("Support", st["h2"]),
              Paragraph("For a Marketplace purchase, use the support conversation available from the product in your PrestaShop Marketplace account. Include module, PrestaShop, and PHP versions plus the queue status and exact error text. Never send access or cron tokens.", st["body"]),
              Paragraph("8. FAQ", st["h1"]),
              Paragraph("Does the module track storefront visitors?", st["h2"]),
              Paragraph("No. It adds no storefront tracker, fingerprint, advertising identifier, or visitor analytics request.", st["body"]),
              Paragraph("Does uninstall delete my Smart Links?", st["h2"]),
              Paragraph("No. Uninstall removes local connector tables and settings only. Remote Smart Links are kept.", st["body"]),
              Paragraph("Can I attach a link and still edit it from PrestaShop?", st["h2"]),
              Paragraph("No. Attached mode is intentionally read-only. Managed mode is required for PrestaShop-driven updates.", st["body"]),
              Paragraph("Does multistore work?", st["h2"]),
              Paragraph("Yes. Configure each shop context independently. Each shop receives a separate installation identity, credentials, queue, cron token, and associations.", st["body"])]

    doc.build(story, onFirstPage=header_footer, onLaterPages=header_footer)
    print(OUTPUT)


if __name__ == "__main__":
    build()
