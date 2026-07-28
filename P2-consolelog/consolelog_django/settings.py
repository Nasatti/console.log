"""
Configurazione principale del progetto Django - consolelog.

Usa SQLite per non richiedere XAMPP o un server MySQL esterno.
Basta eseguire avvia.bat e il progetto parte autonomamente.
"""

from pathlib import Path

# Cartella radice del progetto (quella dove c'è manage.py)
BASE_DIR = Path(__file__).resolve().parent.parent

# Chiave segreta usata da Django per le sessioni e la sicurezza.
SECRET_KEY = 'consolelog-chiave-locale-non-usare-in-produzione'

# Con DEBUG=True Django mostra gli errori nel browser, utile in sviluppo
DEBUG = True

# Accetta richieste solo da localhost
ALLOWED_HOSTS = ['127.0.0.1', 'localhost']


# App installate nel progetto
INSTALLED_APPS = [
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'quiz',  # la nostra applicazione
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
]

ROOT_URLCONF = 'consolelog_django.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
            ],
        },
    },
]

WSGI_APPLICATION = 'consolelog_django.wsgi.application'


# Database SQLite — nessuna installazione esterna necessaria.
# Il file db.sqlite3 viene creato automaticamente nella cartella del progetto.
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.sqlite3',
        'NAME': BASE_DIR / 'db.sqlite3',
    }
}


# Lingua italiana e fuso orario europeo
LANGUAGE_CODE = 'it-it'
TIME_ZONE = 'Europe/Rome'
USE_I18N = True
USE_TZ = False  # False perché lavoriamo con date semplici senza timezone


# File statici (CSS, JS, immagini)
STATIC_URL = '/static/'

# Chiave primaria di default per i modelli Django
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'
