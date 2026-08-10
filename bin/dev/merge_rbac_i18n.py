#!/usr/bin/env python3
"""Merge RBAC roles/permissions/flash/catalog i18n into messages.*.yaml (dev helper)."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TRANS = ROOT / 'translations'

# Shared structure: flash.roles, flash.permissions, roles (UI), permissions (UI + category + catalog)
LOCALES: dict[str, dict] = {
    'es': {
        'flash_roles': {
            'created': 'Rol creado.',
            'updated': 'Rol actualizado.',
            'deleted': 'Rol eliminado.',
            'code_taken': 'Ese código de rol ya está en uso.',
            'system_locked': 'Los roles integrados no se pueden eliminar.',
            'permissions_updated': 'Permisos del rol actualizados.',
            'user_not_found': 'No existe ningún usuario con ese correo.',
            'user_already': 'Ese usuario ya tiene este rol.',
            'user_added': 'Usuario asignado al rol.',
            'user_removed': 'Usuario eliminado del rol.',
        },
        'flash_permissions': {
            'created': 'Permiso creado.',
            'updated': 'Permiso actualizado.',
            'deleted': 'Permiso eliminado.',
            'key_taken': 'Esa clave de permiso ya está en uso.',
            'system_locked': 'Los permisos integrados no se pueden eliminar.',
        },
        'roles': {
            'title': 'Roles',
            'intro': 'Los roles de instancia agrupan claves de permiso. Documentan matrices project.* (espejo de ProjectRole); la Administración de instancia sigue gated por ROLE_ADMIN.',
            'create': 'Nuevo rol',
            'search_placeholder': 'Buscar por nombre, código o descripción…',
            'empty': 'Aún no hay roles. Crea un rol y luego añade permisos y usuarios.',
            'open': 'Abrir',
            'link_permissions': 'Catálogo de permisos',
            'form_intro': 'Los códigos de rol se convierten en valores Symfony ROLE_* del token cuando el rol está activo y asignado.',
            'col': {
                'role': 'Rol',
                'code': 'Código',
                'status': 'Estado',
                'permissions': 'Permisos',
                'users': 'Usuarios',
                'actions': 'Acciones',
            },
            'status': {'enabled': 'Activado', 'disabled': 'Desactivado', 'system': 'Sistema'},
            'back': 'Volver a roles',
            'edit': 'Editar',
            'edit_title': 'Editar rol',
            'save': 'Guardar rol',
            'cancel': 'Cancelar',
            'delete': 'Eliminar rol',
            'delete_confirm': '¿Eliminar este rol? Los usuarios perderán sus permisos.',
            'name_label': 'Nombre',
            'name_help': 'Respaldo en base de datos del nombre visible. La UI prefiere roles.catalog.<slug>.name si esa clave está traducida (slug = código en minúsculas).',
            'code_label': 'Código de seguridad',
            'code_help': 'Se guarda como ROLE_* (p. ej. SUPPORT o ROLE_SUPPORT). Debe ser único.',
            'code_invalid': 'Usa letras, números y guiones bajos (prefijo ROLE_ opcional).',
            'description_label': 'Cuándo usarlo',
            'description_help': 'Orientación breve sobre cuándo asignar este rol. Prefiere roles.catalog.<slug>.description para traducciones; este campo es el respaldo en base de datos.',
            'enabled_label': 'Activado',
            'tabs_label': 'Secciones del rol',
            'tab': {'overview': 'Resumen', 'permissions': 'Permisos', 'users': 'Usuarios'},
            'overview_title': 'Resumen',
            'overview_intro': 'Metadatos del rol y huella de asignación. Usa las pestañas Permisos y Usuarios para la matriz y las cuentas asignadas.',
            'permissions_title': 'Permisos',
            'permissions_intro': 'Marca las capacidades que concede este rol. Los controladores comprobarán estas claves cuando se cableen (ROLE_ADMIN sigue concediendo todo).',
            'permissions_empty': 'Aún no hay permisos en el catálogo. Ejecuta app:seed-platform o crea permisos personalizados.',
            'save_permissions': 'Guardar permisos',
            'users_title': 'Usuarios asignados',
            'users_empty': 'Aún no hay usuarios asignados a este rol.',
            'add_user': 'Asignar usuario',
            'add_user_help': 'La persona debe tener ya una cuenta Beacon.',
            'remove_user': 'Quitar',
            'user_email_placeholder': 'usuario@ejemplo.com',
        },
        'permissions_ui': {
            'title': 'Permisos',
            'intro': 'Claves de capacidad comprobadas con Security isGranted(). Las claves integradas coinciden con las superficies de Administración; se pueden añadir claves personalizadas.',
            'create': 'Nuevo permiso',
            'search_placeholder': 'Buscar por clave, nombre, categoría…',
            'empty': 'Aún no hay permisos. Ejecuta app:seed-platform para cargar el catálogo.',
            'link_roles': 'Gestionar roles',
            'form_intro': 'Las claves de permiso usan notación con puntos (p. ej. admin.mailer.manage) y deben ser únicas.',
            'col': {
                'permission': 'Permiso',
                'key': 'Clave',
                'category': 'Categoría',
                'type': 'Tipo',
                'actions': 'Acciones',
            },
            'type': {'system': 'Sistema', 'custom': 'Personalizado'},
            'edit': 'Editar',
            'edit_title': 'Editar permiso',
            'save': 'Guardar permiso',
            'cancel': 'Cancelar',
            'delete': 'Eliminar',
            'delete_confirm': '¿Eliminar este permiso personalizado? Los roles que lo usaban perderán la concesión.',
            'key_label': 'Clave de permiso',
            'key_help': "Clave en minúsculas con puntos usada en isGranted('project.custom.manage').",
            'key_invalid': 'Usa segmentos en minúsculas separados por puntos (al menos dos), p. ej. project.custom.manage.',
            'name_label': 'Nombre visible',
            'name_help': 'Respaldo en base de datos del nombre visible. La UI prefiere permissions.catalog.<slug>.name si esa clave está traducida (slug = clave con puntos sustituidos por guiones bajos).',
            'category_label': 'Categoría',
            'category_help': 'Elige una categoría. Las etiquetas vienen de permissions.category.<slug>.name / .description.',
            'description_label': 'Descripción',
            'category': {

                'general': {'name': 'General', 'description': 'Claves sin categorizar o heredadas.'},
                'custom': {'name': 'Personalizado', 'description': 'Claves definidas por el operador fuera del catálogo sembrado.'},
            },
        },
        'catalog': {




.'),









 fuera del asistente guiado.'),






        },
        'roles_catalog': {
            'role_support': ('Soporte', 'Operaciones de cuentas más superficies de operaciones en solo lectura para soporte.'),
            'role_ops_viewer': ('Visor ops', 'Hub, resumen ops, log HTTP y docs API en solo lectura.'),
            'role_platform': ('Operador de plataforma', 'Configuración de plataforma (apariencia, correo, Mercure, login social, límites ops, export de config).'),
            'role_nav_editor': ('Editor de navegación', 'Menús, migas de pan, consentimiento de cookies y rutas por idioma.'),
            'role_project_ops': ('Ops de proyectos', 'Administración transversal de proyectos con acceso al hub y al resumen ops.'),
        },
    },
    'de': {
        'flash_roles': {
            'created': 'Rolle erstellt.',
            'updated': 'Rolle aktualisiert.',
            'deleted': 'Rolle gelöscht.',
            'code_taken': 'Dieser Rollencode wird bereits verwendet.',
            'system_locked': 'Integrierte Rollen können nicht gelöscht werden.',
            'permissions_updated': 'Rollenberechtigungen aktualisiert.',
            'user_not_found': 'Kein Benutzer mit dieser E-Mail vorhanden.',
            'user_already': 'Dieser Benutzer hat diese Rolle bereits.',
            'user_added': 'Benutzer der Rolle zugewiesen.',
            'user_removed': 'Benutzer von der Rolle entfernt.',
        },
        'flash_permissions': {
            'created': 'Berechtigung erstellt.',
            'updated': 'Berechtigung aktualisiert.',
            'deleted': 'Berechtigung gelöscht.',
            'key_taken': 'Dieser Berechtigungsschlüssel wird bereits verwendet.',
            'system_locked': 'Integrierte Berechtigungen können nicht gelöscht werden.',
        },
        'roles': {
            'title': 'Rollen',
            'intro': 'Instanzrollen bündeln Berechtigungsschlüssel. Weisen Sie Rollen zu, damit ausgewählte Administrationsbereiche ohne volles ROLE_ADMIN erreichbar sind.',
            'create': 'Neue Rolle',
            'search_placeholder': 'Nach Name, Code oder Beschreibung suchen…',
            'empty': 'Noch keine Rollen. Erstellen Sie eine Rolle und fügen Sie dann Berechtigungen und Benutzer hinzu.',
            'open': 'Öffnen',
            'link_permissions': 'Berechtigungskatalog',
            'form_intro': 'Rollencodes werden zu Symfony ROLE_*-Werten im Token, wenn die Rolle aktiviert und zugewiesen ist.',
            'col': {
                'role': 'Rolle',
                'code': 'Code',
                'status': 'Status',
                'permissions': 'Berechtigungen',
                'users': 'Benutzer',
                'actions': 'Aktionen',
            },
            'status': {'enabled': 'Aktiviert', 'disabled': 'Deaktiviert', 'system': 'System'},
            'back': 'Zurück zu Rollen',
            'edit': 'Bearbeiten',
            'edit_title': 'Rolle bearbeiten',
            'save': 'Rolle speichern',
            'cancel': 'Abbrechen',
            'delete': 'Rolle löschen',
            'delete_confirm': 'Diese Rolle löschen? Benutzer verlieren ihre Berechtigungen.',
            'name_label': 'Name',
            'name_help': 'Datenbank-Fallback für den Anzeigenamen. Die UI bevorzugt roles.catalog.<slug>.name, wenn der Schlüssel übersetzt ist (slug = Code in Kleinbuchstaben).',
            'code_label': 'Sicherheitscode',
            'code_help': 'Wird als ROLE_* gespeichert (z. B. SUPPORT oder ROLE_SUPPORT). Muss eindeutig sein.',
            'code_invalid': 'Buchstaben, Zahlen und Unterstriche verwenden (ROLE_-Präfix optional).',
            'description_label': 'Wann verwenden',
            'description_help': 'Kurze Hinweise, wann diese Rolle zugewiesen werden soll. Bevorzugen Sie roles.catalog.<slug>.description; dieses Feld ist der Datenbank-Fallback.',
            'enabled_label': 'Aktiviert',
            'tabs_label': 'Rollenbereiche',
            'tab': {'overview': 'Übersicht', 'permissions': 'Berechtigungen', 'users': 'Benutzer'},
            'overview_title': 'Übersicht',
            'overview_intro': 'Rollenmetadaten und Zuweisungsübersicht. Nutzen Sie die Registerkarten Berechtigungen und Benutzer für Matrix und Konten.',
            'permissions_title': 'Berechtigungen',
            'permissions_intro': 'Wählen Sie die Fähigkeiten dieser Rolle. Controller prüfen diese Schlüssel nach Verdrahtung (ROLE_ADMIN gewährt weiterhin alles).',
            'permissions_empty': 'Noch keine Berechtigungen im Katalog. Führen Sie app:seed-platform aus oder erstellen Sie benutzerdefinierte Berechtigungen.',
            'save_permissions': 'Berechtigungen speichern',
            'users_title': 'Zugewiesene Benutzer',
            'users_empty': 'Dieser Rolle sind noch keine Benutzer zugewiesen.',
            'add_user': 'Benutzer zuweisen',
            'add_user_help': 'Die Person muss bereits ein Beacon-Konto haben.',
            'remove_user': 'Entfernen',
            'user_email_placeholder': 'benutzer@beispiel.com',
        },
        'permissions_ui': {
            'title': 'Berechtigungen',
            'intro': 'Fähigkeitsschlüssel für Security isGranted(). Eingebaute Schlüssel entsprechen Administrationsflächen; benutzerdefinierte Schlüssel sind möglich.',
            'create': 'Neue Berechtigung',
            'search_placeholder': 'Nach Schlüssel, Name, Kategorie suchen…',
            'empty': 'Noch keine Berechtigungen. Führen Sie app:seed-platform aus, um den Katalog zu laden.',
            'link_roles': 'Rollen verwalten',
            'form_intro': 'Berechtigungsschlüssel nutzen Punktnotation (z. B. admin.mailer.manage) und müssen eindeutig sein.',
            'col': {
                'permission': 'Berechtigung',
                'key': 'Schlüssel',
                'category': 'Kategorie',
                'type': 'Typ',
                'actions': 'Aktionen',
            },
            'type': {'system': 'System', 'custom': 'Benutzerdefiniert'},
            'edit': 'Bearbeiten',
            'edit_title': 'Berechtigung bearbeiten',
            'save': 'Berechtigung speichern',
            'cancel': 'Abbrechen',
            'delete': 'Löschen',
            'delete_confirm': 'Diese benutzerdefinierte Berechtigung löschen? Rollen verlieren die Gewährung.',
            'key_label': 'Berechtigungsschlüssel',
            'key_help': "Kleingeschriebener Punkt-Schlüssel für isGranted('project.custom.manage').",
            'key_invalid': 'Kleingeschriebene Segmente mit Punkten (mindestens zwei), z. B. project.custom.manage.',
            'name_label': 'Anzeigename',
            'name_help': 'Datenbank-Fallback für den Anzeigenamen. Die UI bevorzugt permissions.catalog.<slug>.name (slug = Schlüssel mit Unterstrichen statt Punkten).',
            'category_label': 'Kategorie',
            'category_help': 'Kategorie wählen. Labels kommen aus permissions.category.<slug>.name / .description.',
            'description_label': 'Beschreibung',
            'category': {

                'general': {'name': 'Allgemein', 'description': 'Nicht kategorisierte oder veraltete Berechtigungsschlüssel.'},
                'custom': {'name': 'Benutzerdefiniert', 'description': 'Operatordefinierte Schlüssel außerhalb des geseedeten Katalogs.'},
            },
        },
        'catalog': {




.'),









 außerhalb des geführten Setups.'),






        },
        'roles_catalog': {
            'role_support': ('Support', 'Konto-Ops plus schreibgeschützte Betriebsflächen für Support.'),
            'role_ops_viewer': ('Ops-Betrachter', 'Hub, Ops-Übersicht, HTTP-Log und API-Doku schreibgeschützt.'),
            'role_platform': ('Plattformbetreiber', 'Plattformkonfiguration (Erscheinungsbild, Mailer, Mercure, Social Login, Ops-Standards, Config-Export).'),
            'role_nav_editor': ('Navigationseditor', 'Menüs, Brotkrumen, Cookie-Einwilligung und Locale-Routen.'),
            'role_project_ops': ('Projekt-Ops', 'Projektübergreifende Administration mit Hub- und Ops-Übersicht.'),
        },
    },
}

# French, Italian, Dutch, Portuguese reuse structure with their strings
LOCALES['fr'] = {
    'flash_roles': {
        'created': 'Rôle créé.',
        'updated': 'Rôle mis à jour.',
        'deleted': 'Rôle supprimé.',
        'code_taken': 'Ce code de rôle est déjà utilisé.',
        'system_locked': 'Les rôles intégrés ne peuvent pas être supprimés.',
        'permissions_updated': 'Permissions du rôle mises à jour.',
        'user_not_found': 'Aucun utilisateur avec cet e-mail.',
        'user_already': 'Cet utilisateur a déjà ce rôle.',
        'user_added': 'Utilisateur assigné au rôle.',
        'user_removed': 'Utilisateur retiré du rôle.',
    },
    'flash_permissions': {
        'created': 'Permission créée.',
        'updated': 'Permission mise à jour.',
        'deleted': 'Permission supprimée.',
        'key_taken': 'Cette clé de permission est déjà utilisée.',
        'system_locked': 'Les permissions intégrées ne peuvent pas être supprimées.',
    },
    'roles': {
        'title': 'Rôles',
        'intro': 'Les rôles d’instance regroupent des clés de permission. Assignez des rôles pour accéder à des zones d’Administration sans ROLE_ADMIN complet.',
        'create': 'Nouveau rôle',
        'search_placeholder': 'Rechercher par nom, code ou description…',
        'empty': 'Aucun rôle pour l’instant. Créez un rôle, puis ajoutez permissions et utilisateurs.',
        'open': 'Ouvrir',
        'link_permissions': 'Catalogue de permissions',
        'form_intro': 'Les codes de rôle deviennent des ROLE_* Symfony sur le jeton lorsque le rôle est activé et assigné.',
        'col': {
            'role': 'Rôle',
            'code': 'Code',
            'status': 'Statut',
            'permissions': 'Permissions',
            'users': 'Utilisateurs',
            'actions': 'Actions',
        },
        'status': {'enabled': 'Activé', 'disabled': 'Désactivé', 'system': 'Système'},
        'back': 'Retour aux rôles',
        'edit': 'Modifier',
        'edit_title': 'Modifier le rôle',
        'save': 'Enregistrer le rôle',
        'cancel': 'Annuler',
        'delete': 'Supprimer le rôle',
        'delete_confirm': 'Supprimer ce rôle ? Les utilisateurs perdront ses permissions.',
        'name_label': 'Nom',
        'name_help': 'Secours base de données pour le nom affiché. L’UI préfère roles.catalog.<slug>.name si la clé est traduite (slug = code en minuscules).',
        'code_label': 'Code de sécurité',
        'code_help': 'Stocké comme ROLE_* (ex. SUPPORT ou ROLE_SUPPORT). Doit être unique.',
        'code_invalid': 'Utilisez lettres, chiffres et underscores (préfixe ROLE_ optionnel).',
        'description_label': 'Quand l’utiliser',
        'description_help': 'Courte indication d’usage. Préférez roles.catalog.<slug>.description ; ce champ est le secours base de données.',
        'enabled_label': 'Activé',
        'tabs_label': 'Sections du rôle',
        'tab': {'overview': 'Aperçu', 'permissions': 'Permissions', 'users': 'Utilisateurs'},
        'overview_title': 'Aperçu',
        'overview_intro': 'Métadonnées du rôle et empreinte d’assignation. Utilisez les onglets Permissions et Utilisateurs.',
        'permissions_title': 'Permissions',
        'permissions_intro': 'Cochez les capacités accordées. Les contrôleurs vérifieront ces clés une fois branchées (ROLE_ADMIN accorde encore tout).',
        'permissions_empty': 'Aucune permission dans le catalogue. Exécutez app:seed-platform ou créez des permissions personnalisées.',
        'save_permissions': 'Enregistrer les permissions',
        'users_title': 'Utilisateurs assignés',
        'users_empty': 'Aucun utilisateur assigné à ce rôle.',
        'add_user': 'Assigner un utilisateur',
        'add_user_help': 'La personne doit déjà avoir un compte Beacon.',
        'remove_user': 'Retirer',
        'user_email_placeholder': 'utilisateur@exemple.com',
    },
    'permissions_ui': {
        'title': 'Permissions',
        'intro': 'Clés de capacité vérifiées via Security isGranted(). Les clés intégrées correspondent aux surfaces d’Administration.',
        'create': 'Nouvelle permission',
        'search_placeholder': 'Rechercher par clé, nom, catégorie…',
        'empty': 'Aucune permission. Exécutez app:seed-platform pour charger le catalogue.',
        'link_roles': 'Gérer les rôles',
        'form_intro': 'Les clés utilisent une notation pointée (ex. admin.mailer.manage) et doivent être uniques.',
        'col': {
            'permission': 'Permission',
            'key': 'Clé',
            'category': 'Catégorie',
            'type': 'Type',
            'actions': 'Actions',
        },
        'type': {'system': 'Système', 'custom': 'Personnalisée'},
        'edit': 'Modifier',
        'edit_title': 'Modifier la permission',
        'save': 'Enregistrer la permission',
        'cancel': 'Annuler',
        'delete': 'Supprimer',
        'delete_confirm': 'Supprimer cette permission personnalisée ? Les rôles qui l’utilisent perdront l’octroi.',
        'key_label': 'Clé de permission',
        'key_help': "Clé en minuscules pointée pour isGranted('project.custom.manage').",
        'key_invalid': 'Segments minuscules séparés par des points (au moins deux), ex. project.custom.manage.',
        'name_label': 'Nom affiché',
        'name_help': 'Secours base de données. L’UI préfère permissions.catalog.<slug>.name (slug = clé avec underscores).',
        'category_label': 'Catégorie',
        'category_help': 'Choisir une catégorie. Les libellés viennent de permissions.category.<slug>.name / .description.',
        'description_label': 'Description',
        'category': {

            'general': {'name': 'Général', 'description': 'Clés non catégorisées ou héritées.'},
            'custom': {'name': 'Personnalisé', 'description': 'Clés définies par l’opérateur hors du catalogue seedé.'},
        },
    },
    'catalog': {




.'),









 hors assistant guidé.'),






    },
    'roles_catalog': {
        'role_support': ('Support', 'Ops comptes plus surfaces opérations en lecture seule pour le support.'),
        'role_ops_viewer': ('Lecteur ops', 'Hub, vue ops, journal HTTP et docs API en lecture seule.'),
        'role_platform': ('Opérateur plateforme', 'Configuration plateforme (apparence, mailer, Mercure, connexion sociale, limites ops, export de config).'),
        'role_nav_editor': ('Éditeur de navigation', 'Menus, fil d’Ariane, consentement cookies et routes par locale.'),
        'role_project_ops': ('Ops projets', 'Administration transversale des projets avec hub et vue ops.'),
    },
}

LOCALES['it'] = {
    'flash_roles': {
        'created': 'Ruolo creato.',
        'updated': 'Ruolo aggiornato.',
        'deleted': 'Ruolo eliminato.',
        'code_taken': 'Questo codice ruolo è già in uso.',
        'system_locked': 'I ruoli integrati non possono essere eliminati.',
        'permissions_updated': 'Permessi del ruolo aggiornati.',
        'user_not_found': 'Nessun utente con questa e-mail.',
        'user_already': 'Questo utente ha già questo ruolo.',
        'user_added': 'Utente assegnato al ruolo.',
        'user_removed': 'Utente rimosso dal ruolo.',
    },
    'flash_permissions': {
        'created': 'Permesso creato.',
        'updated': 'Permesso aggiornato.',
        'deleted': 'Permesso eliminato.',
        'key_taken': 'Questa chiave permesso è già in uso.',
        'system_locked': 'I permessi integrati non possono essere eliminati.',
    },
    'roles': {
        'title': 'Ruoli',
        'intro': 'I ruoli di istanza raggruppano chiavi di permesso. Assegna ruoli per accedere ad aree di Amministrazione senza ROLE_ADMIN completo.',
        'create': 'Nuovo ruolo',
        'search_placeholder': 'Cerca per nome, codice o descrizione…',
        'empty': 'Nessun ruolo ancora. Crea un ruolo, poi aggiungi permessi e utenti.',
        'open': 'Apri',
        'link_permissions': 'Catalogo permessi',
        'form_intro': 'I codici ruolo diventano ROLE_* Symfony sul token quando il ruolo è abilitato e assegnato.',
        'col': {
            'role': 'Ruolo',
            'code': 'Codice',
            'status': 'Stato',
            'permissions': 'Permessi',
            'users': 'Utenti',
            'actions': 'Azioni',
        },
        'status': {'enabled': 'Abilitato', 'disabled': 'Disabilitato', 'system': 'Sistema'},
        'back': 'Torna ai ruoli',
        'edit': 'Modifica',
        'edit_title': 'Modifica ruolo',
        'save': 'Salva ruolo',
        'cancel': 'Annulla',
        'delete': 'Elimina ruolo',
        'delete_confirm': 'Eliminare questo ruolo? Gli utenti perderanno i suoi permessi.',
        'name_label': 'Nome',
        'name_help': 'Fallback database per il nome visualizzato. L’UI preferisce roles.catalog.<slug>.name se tradotto (slug = codice minuscolo).',
        'code_label': 'Codice di sicurezza',
        'code_help': 'Salvato come ROLE_* (es. SUPPORT o ROLE_SUPPORT). Deve essere univoco.',
        'code_invalid': 'Usa lettere, numeri e underscore (prefisso ROLE_ opzionale).',
        'description_label': 'Quando usarlo',
        'description_help': 'Breve guida su quando assegnare questo ruolo. Preferisci roles.catalog.<slug>.description; questo campo è il fallback database.',
        'enabled_label': 'Abilitato',
        'tabs_label': 'Sezioni del ruolo',
        'tab': {'overview': 'Panoramica', 'permissions': 'Permessi', 'users': 'Utenti'},
        'overview_title': 'Panoramica',
        'overview_intro': 'Metadati del ruolo e impronta di assegnazione. Usa le schede Permessi e Utenti.',
        'permissions_title': 'Permessi',
        'permissions_intro': 'Seleziona le capacità concesse. I controller controlleranno queste chiavi una volta cablate (ROLE_ADMIN concede ancora tutto).',
        'permissions_empty': 'Nessun permesso nel catalogo. Esegui app:seed-platform o crea permessi personalizzati.',
        'save_permissions': 'Salva permessi',
        'users_title': 'Utenti assegnati',
        'users_empty': 'Nessun utente assegnato a questo ruolo.',
        'add_user': 'Assegna utente',
        'add_user_help': 'La persona deve già avere un account Beacon.',
        'remove_user': 'Rimuovi',
        'user_email_placeholder': 'utente@esempio.com',
    },
    'permissions_ui': {
        'title': 'Permessi',
        'intro': 'Chiavi di capacità verificate con Security isGranted(). Le chiavi integrate corrispondono alle superfici di Amministrazione.',
        'create': 'Nuovo permesso',
        'search_placeholder': 'Cerca per chiave, nome, categoria…',
        'empty': 'Nessun permesso. Esegui app:seed-platform per caricare il catalogo.',
        'link_roles': 'Gestisci ruoli',
        'form_intro': 'Le chiavi usano notazione a punti (es. admin.mailer.manage) e devono essere univoche.',
        'col': {
            'permission': 'Permesso',
            'key': 'Chiave',
            'category': 'Categoria',
            'type': 'Tipo',
            'actions': 'Azioni',
        },
        'type': {'system': 'Sistema', 'custom': 'Personalizzato'},
        'edit': 'Modifica',
        'edit_title': 'Modifica permesso',
        'save': 'Salva permesso',
        'cancel': 'Annulla',
        'delete': 'Elimina',
        'delete_confirm': 'Eliminare questo permesso personalizzato? I ruoli che lo usavano perderanno la concessione.',
        'key_label': 'Chiave permesso',
        'key_help': "Chiave minuscola a punti usata in isGranted('project.custom.manage').",
        'key_invalid': 'Segmenti minuscoli separati da punti (almeno due), es. project.custom.manage.',
        'name_label': 'Nome visualizzato',
        'name_help': 'Fallback database. L’UI preferisce permissions.catalog.<slug>.name (slug = chiave con underscore).',
        'category_label': 'Categoria',
        'category_help': 'Etichetta di raggruppamento per la matrice (access, instance, custom, …).',
        'description_label': 'Descrizione',
        'category': {

            'general': {'name': 'Generale', 'description': 'Chiavi non categorizzate o legacy.'},
            'custom': {'name': 'Personalizzato', 'description': 'Chiavi definite dall’operatore fuori dal catalogo seedato.'},
        },
    },
    'catalog': {




.'),









 fuori dalla procedura guidata.'),






    },
    'roles_catalog': {
        'role_support': ('Supporto', 'Ops account più superfici operazioni in sola lettura per il supporto.'),
        'role_ops_viewer': ('Visualizzatore ops', 'Hub, panoramica ops, log HTTP e docs API in sola lettura.'),
        'role_platform': ('Operatore piattaforma', 'Configurazione piattaforma (aspetto, mailer, Mercure, login social, limiti ops, export config).'),
        'role_nav_editor': ('Editor navigazione', 'Menu, breadcrumb, consenso cookie e route per locale.'),
        'role_project_ops': ('Ops progetti', 'Amministrazione trasversale progetti con hub e panoramica ops.'),
    },
}

LOCALES['nl'] = {
    'flash_roles': {
        'created': 'Rol aangemaakt.',
        'updated': 'Rol bijgewerkt.',
        'deleted': 'Rol verwijderd.',
        'code_taken': 'Die rolcode is al in gebruik.',
        'system_locked': 'Ingebouwde rollen kunnen niet worden verwijderd.',
        'permissions_updated': 'Rolrechten bijgewerkt.',
        'user_not_found': 'Geen gebruiker met dat e-mailadres.',
        'user_already': 'Die gebruiker heeft deze rol al.',
        'user_added': 'Gebruiker aan de rol toegewezen.',
        'user_removed': 'Gebruiker van de rol verwijderd.',
    },
    'flash_permissions': {
        'created': 'Recht aangemaakt.',
        'updated': 'Recht bijgewerkt.',
        'deleted': 'Recht verwijderd.',
        'key_taken': 'Die rechtensleutel is al in gebruik.',
        'system_locked': 'Ingebouwde rechten kunnen niet worden verwijderd.',
    },
    'roles': {
        'title': 'Rollen',
        'intro': 'Instancierollen bundelen rechtensleutels. Wijs rollen toe zodat geselecteerde Administratie-onderdelen bereikbaar zijn zonder volledig ROLE_ADMIN.',
        'create': 'Nieuwe rol',
        'search_placeholder': 'Zoek op naam, code of beschrijving…',
        'empty': 'Nog geen rollen. Maak een rol en voeg daarna rechten en gebruikers toe.',
        'open': 'Openen',
        'link_permissions': 'Rechtencatalogus',
        'form_intro': 'Rolcodes worden Symfony ROLE_*-waarden op het token wanneer de rol is ingeschakeld en toegewezen.',
        'col': {
            'role': 'Rol',
            'code': 'Code',
            'status': 'Status',
            'permissions': 'Rechten',
            'users': 'Gebruikers',
            'actions': 'Acties',
        },
        'status': {'enabled': 'Ingeschakeld', 'disabled': 'Uitgeschakeld', 'system': 'Systeem'},
        'back': 'Terug naar rollen',
        'edit': 'Bewerken',
        'edit_title': 'Rol bewerken',
        'save': 'Rol opslaan',
        'cancel': 'Annuleren',
        'delete': 'Rol verwijderen',
        'delete_confirm': 'Deze rol verwijderen? Gebruikers verliezen de rechten.',
        'name_label': 'Naam',
        'name_help': 'Database-fallback voor de weergavenaam. De UI verkiest roles.catalog.<slug>.name als die sleutel vertaald is (slug = code in kleine letters).',
        'code_label': 'Beveiligingscode',
        'code_help': 'Opgeslagen als ROLE_* (bijv. SUPPORT of ROLE_SUPPORT). Moet uniek zijn.',
        'code_invalid': 'Gebruik letters, cijfers en underscores (ROLE_-prefix optioneel).',
        'description_label': 'Wanneer gebruiken',
        'description_help': 'Korte toelichting wanneer deze rol toe te wijzen. Verkies roles.catalog.<slug>.description; dit veld is de database-fallback.',
        'enabled_label': 'Ingeschakeld',
        'tabs_label': 'Rolsecties',
        'tab': {'overview': 'Overzicht', 'permissions': 'Rechten', 'users': 'Gebruikers'},
        'overview_title': 'Overzicht',
        'overview_intro': 'Rolmetadata en toewijzingsoverzicht. Gebruik de tabbladen Rechten en Gebruikers.',
        'permissions_title': 'Rechten',
        'permissions_intro': 'Vink de capabilities van deze rol aan. Controllers controleren deze sleutels zodra ze zijn aangesloten (ROLE_ADMIN geeft nog steeds alles).',
        'permissions_empty': 'Nog geen rechten in de catalogus. Voer app:seed-platform uit of maak aangepaste rechten.',
        'save_permissions': 'Rechten opslaan',
        'users_title': 'Toegewezen gebruikers',
        'users_empty': 'Nog geen gebruikers aan deze rol toegewezen.',
        'add_user': 'Gebruiker toewijzen',
        'add_user_help': 'De persoon moet al een Beacon-account hebben.',
        'remove_user': 'Verwijderen',
        'user_email_placeholder': 'gebruiker@voorbeeld.com',
    },
    'permissions_ui': {
        'title': 'Rechten',
        'intro': 'Capability-sleutels gecontroleerd via Security isGranted(). Ingebouwde sleutels komen overeen met Administratie-oppervlakken.',
        'create': 'Nieuw recht',
        'search_placeholder': 'Zoek op sleutel, naam, categorie…',
        'empty': 'Nog geen rechten. Voer app:seed-platform uit om de catalogus te laden.',
        'link_roles': 'Rollen beheren',
        'form_intro': 'Rechtensleutels gebruiken puntnotatie (bijv. admin.mailer.manage) en moeten uniek zijn.',
        'col': {
            'permission': 'Recht',
            'key': 'Sleutel',
            'category': 'Categorie',
            'type': 'Type',
            'actions': 'Acties',
        },
        'type': {'system': 'Systeem', 'custom': 'Aangepast'},
        'edit': 'Bewerken',
        'edit_title': 'Recht bewerken',
        'save': 'Recht opslaan',
        'cancel': 'Annuleren',
        'delete': 'Verwijderen',
        'delete_confirm': 'Dit aangepaste recht verwijderen? Rollen die het gebruikten verliezen de toekenning.',
        'key_label': 'Rechtensleutel',
        'key_help': "Kleine letters met punten voor isGranted('project.custom.manage').",
        'key_invalid': 'Segmenten in kleine letters met punten (minstens twee), bijv. project.custom.manage.',
        'name_label': 'Weergavenaam',
        'name_help': 'Database-fallback. De UI verkiest permissions.catalog.<slug>.name (slug = sleutel met underscores).',
        'category_label': 'Categorie',
        'category_help': 'Groeperingslabel voor de rechtenmatrix (access, instance, custom, …).',
        'description_label': 'Beschrijving',
        'category': {

            'general': {'name': 'Algemeen', 'description': 'Niet-gecategoriseerde of legacy-rechtensleutels.'},
            'custom': {'name': 'Aangepast', 'description': 'Door de operator gedefinieerde sleutels buiten de geseede catalogus.'},
        },
    },
    'catalog': {




.'),









 buiten de begeleidde setup.'),






    },
    'roles_catalog': {
        'role_support': ('Support', 'Account-ops plus alleen-lezen operatievlakken voor support.'),
        'role_ops_viewer': ('Ops-kijker', 'Hub, ops-overzicht, HTTP-log en API-docs alleen-lezen.'),
        'role_platform': ('Platformoperator', 'Platformconfiguratie (uiterlijk, mailer, Mercure, social login, ops-defaults, config-export).'),
        'role_nav_editor': ('Navigatie-editor', 'Menu’s, breadcrumbs, cookieconsent en locale-routes.'),
        'role_project_ops': ('Project-ops', 'Projectoverschrijdend beheer met hub- en ops-overzicht.'),
    },
}

LOCALES['pt'] = {
    'flash_roles': {
        'created': 'Função criada.',
        'updated': 'Função atualizada.',
        'deleted': 'Função eliminada.',
        'code_taken': 'Esse código de função já está em uso.',
        'system_locked': 'Funções integradas não podem ser eliminadas.',
        'permissions_updated': 'Permissões da função atualizadas.',
        'user_not_found': 'Não existe utilizador com esse e-mail.',
        'user_already': 'Esse utilizador já tem esta função.',
        'user_added': 'Utilizador atribuído à função.',
        'user_removed': 'Utilizador removido da função.',
    },
    'flash_permissions': {
        'created': 'Permissão criada.',
        'updated': 'Permissão atualizada.',
        'deleted': 'Permissão eliminada.',
        'key_taken': 'Essa chave de permissão já está em uso.',
        'system_locked': 'Permissões integradas não podem ser eliminadas.',
    },
    'roles': {
        'title': 'Funções',
        'intro': 'As funções de instância agrupam chaves de permissão. Atribua funções para aceder a áreas de Administração sem ROLE_ADMIN completo.',
        'create': 'Nova função',
        'search_placeholder': 'Pesquisar por nome, código ou descrição…',
        'empty': 'Ainda sem funções. Crie uma função e depois adicione permissões e utilizadores.',
        'open': 'Abrir',
        'link_permissions': 'Catálogo de permissões',
        'form_intro': 'Os códigos de função tornam-se ROLE_* Symfony no token quando a função está ativa e atribuída.',
        'col': {
            'role': 'Função',
            'code': 'Código',
            'status': 'Estado',
            'permissions': 'Permissões',
            'users': 'Utilizadores',
            'actions': 'Ações',
        },
        'status': {'enabled': 'Ativada', 'disabled': 'Desativada', 'system': 'Sistema'},
        'back': 'Voltar às funções',
        'edit': 'Editar',
        'edit_title': 'Editar função',
        'save': 'Guardar função',
        'cancel': 'Cancelar',
        'delete': 'Eliminar função',
        'delete_confirm': 'Eliminar esta função? Os utilizadores perderão as permissões.',
        'name_label': 'Nome',
        'name_help': 'Fallback na base de dados para o nome visível. A UI prefere roles.catalog.<slug>.name se a chave estiver traduzida (slug = código em minúsculas).',
        'code_label': 'Código de segurança',
        'code_help': 'Guardado como ROLE_* (ex. SUPPORT ou ROLE_SUPPORT). Deve ser único.',
        'code_invalid': 'Use letras, números e underscores (prefixo ROLE_ opcional).',
        'description_label': 'Quando usar',
        'description_help': 'Orientação breve sobre quando atribuir esta função. Prefira roles.catalog.<slug>.description; este campo é o fallback na base de dados.',
        'enabled_label': 'Ativada',
        'tabs_label': 'Secções da função',
        'tab': {'overview': 'Resumo', 'permissions': 'Permissões', 'users': 'Utilizadores'},
        'overview_title': 'Resumo',
        'overview_intro': 'Metadados da função e pegada de atribuição. Use os separadores Permissões e Utilizadores.',
        'permissions_title': 'Permissões',
        'permissions_intro': 'Marque as capacidades desta função. Os controladores verificarão estas chaves quando forem ligadas (ROLE_ADMIN continua a conceder tudo).',
        'permissions_empty': 'Ainda sem permissões no catálogo. Execute app:seed-platform ou crie permissões personalizadas.',
        'save_permissions': 'Guardar permissões',
        'users_title': 'Utilizadores atribuídos',
        'users_empty': 'Ainda sem utilizadores atribuídos a esta função.',
        'add_user': 'Atribuir utilizador',
        'add_user_help': 'A pessoa já deve ter uma conta Beacon.',
        'remove_user': 'Remover',
        'user_email_placeholder': 'utilizador@exemplo.com',
    },
    'permissions_ui': {
        'title': 'Permissões',
        'intro': 'Chaves de capacidade verificadas com Security isGranted(). As chaves integradas correspondem às superfícies de Administração.',
        'create': 'Nova permissão',
        'search_placeholder': 'Pesquisar por chave, nome, categoria…',
        'empty': 'Ainda sem permissões. Execute app:seed-platform para carregar o catálogo.',
        'link_roles': 'Gerir funções',
        'form_intro': 'As chaves usam notação com pontos (ex. admin.mailer.manage) e devem ser únicas.',
        'col': {
            'permission': 'Permissão',
            'key': 'Chave',
            'category': 'Categoria',
            'type': 'Tipo',
            'actions': 'Ações',
        },
        'type': {'system': 'Sistema', 'custom': 'Personalizada'},
        'edit': 'Editar',
        'edit_title': 'Editar permissão',
        'save': 'Guardar permissão',
        'cancel': 'Cancelar',
        'delete': 'Eliminar',
        'delete_confirm': 'Eliminar esta permissão personalizada? As funções que a usavam perderão a concessão.',
        'key_label': 'Chave de permissão',
        'key_help': "Chave em minúsculas com pontos usada em isGranted('project.custom.manage').",
        'key_invalid': 'Segmentos em minúsculas separados por pontos (pelo menos dois), ex. project.custom.manage.',
        'name_label': 'Nome visível',
        'name_help': 'Fallback na base de dados. A UI prefere permissions.catalog.<slug>.name (slug = chave com underscores).',
        'category_label': 'Categoria',
        'category_help': 'Etiqueta de agrupamento para a matriz (access, instance, custom, …).',
        'description_label': 'Descrição',
        'category': {

            'general': {'name': 'Geral', 'description': 'Chaves sem categoria ou legadas.'},
            'custom': {'name': 'Personalizado', 'description': 'Chaves definidas pelo operador fora do catálogo seedado.'},
        },
    },
    'catalog': {




.'),









 fora do assistente guiado.'),






    },
    'roles_catalog': {
        'role_support': ('Suporte', 'Ops de contas mais superfícies de operações só de leitura para suporte.'),
        'role_ops_viewer': ('Visualizador ops', 'Hub, resumo ops, log HTTP e docs da API só de leitura.'),
        'role_platform': ('Operador de plataforma', 'Configuração da plataforma (aparência, mailer, Mercure, login social, limites ops, export de config).'),
        'role_nav_editor': ('Editor de navegação', 'Menus, breadcrumbs, consentimento de cookies e rotas por locale.'),
        'role_project_ops': ('Ops de projetos', 'Administração transversal de projetos com hub e resumo ops.'),
    },
}


def yaml_escape(value: str) -> str:
    if value == '':
        return "''"
    needs_quotes = (
        value.startswith((' ', '#', '@', '`', '|', '>', '*', '&', '!', '%', '?'))
        or value[0] in '0123456789'
        or any(c in value for c in [':', '#', '{', '}', '[', ']', ',', '&', '*', '?', '|', '-', '<', '>', '=', '!', '%', '@', '`', '\n', '"', "'"])
        or value.strip() != value
        or value.lower() in {'true', 'false', 'null', 'yes', 'no', 'on', 'off'}
    )
    if needs_quotes:
        return "'" + value.replace("'", "''") + "'"
    return value


def dump_mapping(data: dict, indent: int = 0) -> list[str]:
    lines: list[str] = []
    pad = ' ' * indent
    for key, value in data.items():
        if isinstance(value, dict):
            lines.append(f'{pad}{key}:')
            lines.extend(dump_mapping(value, indent + 4))
        else:
            lines.append(f'{pad}{key}: {yaml_escape(str(value))}')
    return lines


def build_roles_block(data: dict) -> str:
    roles = dict(data['roles'])
    if 'roles_catalog' in data:
        roles['catalog'] = {
            slug: {'name': name, 'description': desc}
            for slug, (name, desc) in data['roles_catalog'].items()
        }
    return 'roles:\n' + '\n'.join(dump_mapping(roles, 4)) + '\n'


def build_permissions_block(data: dict) -> str:
    ui = dict(data['permissions_ui'])
    catalog = {
        slug: {'name': name, 'description': desc}
        for slug, (name, desc) in data['catalog'].items()
    }
    ui['catalog'] = catalog
    return 'permissions:\n' + '\n'.join(dump_mapping(ui, 4)) + '\n'


def build_flash_fragment(data: dict) -> str:
    lines = ['    roles:']
    lines.extend(dump_mapping(data['flash_roles'], 8))
    lines.append('    permissions:')
    lines.extend(dump_mapping(data['flash_permissions'], 8))
    return '\n'.join(lines) + '\n'


def ensure_flash(text: str, fragment: str) -> str:
    if re.search(r'^    roles:\n(?:        .+\n)+    permissions:\n', text, re.M):
        # Replace existing flash.roles + flash.permissions blocks under flash
        text = re.sub(
            r'^    roles:\n(?:        .+\n)+    permissions:\n(?:        .+\n)+',
            fragment,
            text,
            count=1,
            flags=re.M,
        )
        return text
    if '    roles:\n' in text and 'flash:' in text:
        # Partial — remove old flash.roles if present alone
        text = re.sub(r'^    roles:\n(?:        .+\n)+', '', text, count=1, flags=re.M)
    # Insert after flash.groups block (ends before next 4-space key under flash or next top-level)
    m = re.search(r'(^    groups:\n(?:        .+\n)+)', text, re.M)
    if not m:
        raise SystemExit('Could not find flash.groups block to insert after')
    insert_at = m.end()
    return text[:insert_at] + fragment + text[insert_at:]


def replace_top_level_block(text: str, key: str, block: str) -> str:
    # Only consume direct children (exactly 4 spaces), not deeper nested leftovers.
    pattern = re.compile(
        rf'^{re.escape(key)}:\n(?:    [^ \n].*\n|    \n|\n)*',
        re.M,
    )
    if pattern.search(text):
        return pattern.sub(block if block.endswith('\n') else block + '\n', text, count=1)
    # Insert before groups:
    marker = '\ngroups:\n'
    idx = text.find(marker)
    if idx == -1:
        # append before end
        return text.rstrip() + '\n\n' + block + '\n'
    return text[: idx + 1] + block + text[idx + 1 :]


def main() -> None:
    for locale, data in LOCALES.items():
        path = TRANS / f'messages.{locale}.yaml'
        text = path.read_text(encoding='utf-8')
        text = ensure_flash(text, build_flash_fragment(data))
        text = replace_top_level_block(text, 'roles', build_roles_block(data))
        text = replace_top_level_block(text, 'permissions', build_permissions_block(data))
        path.write_text(text, encoding='utf-8')
        print(f'updated {path.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
