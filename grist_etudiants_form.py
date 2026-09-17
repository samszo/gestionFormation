"""Provisionne la table "etudiants" sur laquelle repose le formulaire Grist public
(nom, prénom, mail, n° étudiant, promotions, compte GitHub, photo).

Un "formulaire Grist public" (Page > Ajouter > Formulaire) n'est pas un objet que l'API REST de
Grist sait créer : sa mise en page se fait dans l'éditeur de formulaire, dans l'interface Grist.
Ce script crée en revanche, via l'API REST, la table et les colonnes que ce formulaire remplira,
avec les bons types (liste déroulante = référence vers la table "promotions", photo = pièce
jointe) et une colonne de vérification de la saisie du mail — le formulaire n'a alors plus qu'à
être créé dans l'UI en le pointant sur cette table.

Utilisation :
    export GRIST_API_KEY="..."          # Mon compte > Paramètres du compte > clé API
    export GRIST_DOC_ID="..."           # id du document (visible dans son URL)
    export GRIST_SERVER="https://docs.getgrist.com"   # optionnel, valeur par défaut
    python3 grist_etudiants_form.py
"""

import os
import sys
import requests

GRIST_SERVER = os.environ.get("GRIST_SERVER", "https://docs.getgrist.com")
GRIST_API_KEY = os.environ.get("GRIST_API_KEY")
GRIST_DOC_ID = os.environ.get("GRIST_DOC_ID")

TABLE_ETUDIANTS = "etudiants"
TABLE_PROMOTIONS = "promotions"

# formule Python évaluée par Grist à chaque saisie/modification d'un mail : vérifie le format et
# renvoie un message d'erreur (chaîne vide si le mail est valide). C'est ce texte qui peut être
# affiché comme condition d'alerte ("Règle" de mise en forme) sur le champ Mail du formulaire.
FORMULE_MAIL_ERREUR = (
    "import re\n"
    "mail = ($Mail or '').strip()\n"
    "if not mail:\n"
    "    return 'Mail requis'\n"
    "if not re.match(r'^[^@\\s]+@[^@\\s]+\\.[a-zA-Z]{2,}$', mail):\n"
    "    return 'Format de mail invalide'\n"
    "return ''"
)


def api_headers():
    if not GRIST_API_KEY:
        sys.exit("GRIST_API_KEY manquant (variable d'environnement).")
    return {"Authorization": "Bearer " + GRIST_API_KEY, "Content-Type": "application/json"}


def api_url(path):
    if not GRIST_DOC_ID:
        sys.exit("GRIST_DOC_ID manquant (variable d'environnement).")
    return "{}/api/docs/{}{}".format(GRIST_SERVER, GRIST_DOC_ID, path)


def list_tables():
    r = requests.get(api_url("/tables"), headers=api_headers())
    r.raise_for_status()
    return [t["id"] for t in r.json().get("tables", [])]


def list_columns(table_id):
    r = requests.get(api_url("/tables/{}/columns".format(table_id)), headers=api_headers())
    r.raise_for_status()
    return r.json().get("columns", [])


def visible_col_ref(table_id, col_id):
    """colRef numérique de la colonne `col_id` de `table_id`, pour l'utiliser comme colonne
    affichée (visibleCol) d'une référence — nécessaire pour que le menu déroulant "promotions"
    affiche le code de la promotion plutôt que son numéro de ligne interne."""
    for c in list_columns(table_id):
        if c["id"] == col_id:
            return c["fields"].get("colRef") or c["id"]
    return None


def colonnes_etudiants(promotions_visible_col_ref):
    return [
        {"id": "Nom", "fields": {"label": "Nom", "type": "Text"}},
        {"id": "Prenom", "fields": {"label": "Prénom", "type": "Text"}},
        {"id": "Mail", "fields": {"label": "Mail", "type": "Text"}},
        {"id": "N_etudiant", "fields": {"label": "N° étudiant", "type": "Text"}},
        {
            "id": "Promotions",
            "fields": {
                "label": "Promotions",
                "type": "Ref:{}".format(TABLE_PROMOTIONS.capitalize()),
                "visibleCol": promotions_visible_col_ref,
            },
        },
        {"id": "Compte_GitHub", "fields": {"label": "Compte GitHub", "type": "Text"}},
        {"id": "Photo", "fields": {"label": "Photo", "type": "Attachments"}},
        {
            "id": "Mail_erreur",
            "fields": {
                "label": "Erreur mail",
                "type": "Text",
                "isFormula": True,
                "formula": FORMULE_MAIL_ERREUR,
            },
        },
    ]


def creer_table_etudiants():
    tables = list_tables()
    if TABLE_PROMOTIONS not in tables:
        sys.exit(
            'Table "{}" introuvable dans le document : elle doit exister avant "{}" '
            "(colonne de référence pour la liste déroulante).".format(TABLE_PROMOTIONS, TABLE_ETUDIANTS)
        )

    # colonne affichée dans le menu déroulant "Promotions" : le code de la promotion
    visible_ref = visible_col_ref(TABLE_PROMOTIONS, "code") or visible_col_ref(TABLE_PROMOTIONS, "Code")
    colonnes = colonnes_etudiants(visible_ref)

    if TABLE_ETUDIANTS in tables:
        existantes = {c["id"] for c in list_columns(TABLE_ETUDIANTS)}
        a_ajouter = [c for c in colonnes if c["id"] not in existantes]
        if not a_ajouter:
            print('Table "{}" déjà à jour.'.format(TABLE_ETUDIANTS))
            return
        r = requests.post(
            api_url("/tables/{}/columns".format(TABLE_ETUDIANTS)),
            headers=api_headers(),
            json={"columns": a_ajouter},
        )
        r.raise_for_status()
        print('Colonnes ajoutées à "{}" : {}'.format(TABLE_ETUDIANTS, [c["id"] for c in a_ajouter]))
    else:
        r = requests.post(
            api_url("/tables"),
            headers=api_headers(),
            json={"tables": [{"id": TABLE_ETUDIANTS, "columns": colonnes}]},
        )
        r.raise_for_status()
        print('Table "{}" créée avec les colonnes : {}'.format(TABLE_ETUDIANTS, [c["id"] for c in colonnes]))

    print(
        "\nIl reste à créer le formulaire public dans l'UI Grist : "
        'sur une page, "Ajouter" > "Formulaire", en le liant à la table "{}". '
        'Pour le champ Mail, choisir le type de champ "Email" dans l\'éditeur de formulaire '
        "(validation du format côté navigateur) ; la colonne Mail_erreur créée ci-dessus reste "
        "renseignée même pour une saisie faite hors formulaire (import, API...).".format(TABLE_ETUDIANTS)
    )


if __name__ == "__main__":
    creer_table_etudiants()
