#
# Grader configuration
#

WORKER_COUNT = 3  # Threads

# Will not work with random UTF-8 characters since the utf8_encode() algorithm
# in PHP and Python is apparently different. Will work with Latin letters, digits and most symbols.
AUTH_USERNAME = "username"
AUTH_PASSWORD = "password"

PATH_DATA = "data/"
PATH_TESTS = "data/tests/"
PATH_SANDBOX = "sandbox/"

SOURCE_NAME = "source"
EXECUTABLE_NAME = "executable"

FILE_DOWNLOAD_CHUNK_SIZE = 1048576  # 1MB of data

UPDATE_INTERVAL = 0.5  # Seconds
