# Generated by Django 2.2.5 on 2019-10-03 07:41

from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('employee', '0003_auto_20190929_0524'),
    ]

    operations = [
        migrations.AddField(
            model_name='profile',
            name='profile_picture',
            field=models.ImageField(blank=True, max_length=255, null=True, upload_to='profilePicture'),
        ),
    ]
