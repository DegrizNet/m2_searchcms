# Magento 2 - Search CMS Pages

This module displays search results for CMS page hits. The results are displayed at the top of the search page.

## Author

Anže Voh  
[Magento eCommerce Developer](https://www.degriz.net/) at Degriz

## Installation

Follow these steps to install the module:

1. **Download the Module:** Obtain the module from this repository.
2. **Upload the Files:** Create the folder `app/code/Magelan/MinicartTotal` and copy the contents of the module into this folder.
3. **Enable the Module and Run Setup:** Use the following commands in your terminal (for production mode):

    ```sh
    php bin/magento maintenance:enable 
    php bin/magento module:enable Magelan_SearchCms
    php bin/magento setup:upgrade
    php -dmemory_limit=4G bin/magento setup:di:compile
    rm -rf pub/static/_requirejs var/view_preprocessed pub/static/frontend/ pub/static/adminhtml/  
    php bin/magento setup:static-content:deploy -f
    php bin/magento maintenance:disable
    php bin/magento cache:flush
    ```

## Notice

- Always test the module on a development version before deploying it to your production environment.
- Ensure to review and manage your privacy settings in accordance with legal requirements and best practices.

## License

This project is licensed under the [MIT License](LICENSE).

## Disclaimer

- This module is provided "as is," without any warranty of any kind, express or implied. Use it at your own risk.
- The author takes no responsibility for any issues or problems that arise from using this module.
- Free support is not provided.
- You are free to use and modify this module, but you cannot resell it.

## Additional Information

I specialize in custom Magento development and have successfully completed numerous projects tailored to optimize eCommerce functionalities. My services include:

- **Custom Module Development:** Creating tailored solutions to meet your specific business needs.
- **Integration Services:** Implementing and integrating third-party services such as Snap Pixel to enhance your advertising efforts.
- **Performance Optimization:** Ensuring that your Magento store runs efficiently and provides a seamless user experience.
- **Ongoing Support and Maintenance:** Offering support to keep your Magento store up-to-date and running smoothly.

For more information or inquiries, please visit [Degriz Magento eCommerce Development](https://www.degriz.net/).
