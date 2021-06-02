from setuptools import setup, find_packages

setup(
    name="Action",
    version="1.0",
    description="A powerful back-end for programming competition systems.",
    author="Alexander Georgiev",
    author_email="thinkcreative@outlook.com",
    keywords="Competitive programming, Sandbox, Evaluation",
    url="http://action.informatika.bg",
    project_urls={
        "Bug Tracker": "http://www.espr1t.net/bugs",
        "Source Code": "https://github.com/espr1t/action",
    },

    packages=find_packages(),

    install_requires=[
        "Flask~=1.1.1",
        "urllib3==1.26.5",
        "requests==2.23.0",
        "vcrpy~=4.0.2",
        "pyflakes~=2.2.0",
        "psutil~=5.7.0",
        "pytest~=5.4.0",
        "numpy~=1.18",
    ],
)
