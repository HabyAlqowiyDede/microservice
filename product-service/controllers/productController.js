const productModel = require('../models/productModel');
const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

function validateBase64Image(image) {
    if (image === undefined || image === null ||
        (typeof image === 'string' && image.trim() === '')) {
        return { error: 'Field image wajib diisi', image: null };
    }
    if (typeof image !== 'string') {
        return { error: 'Field image harus berupa Base64 yang valid', image: null };
    }
    const cleanImage = image.trim().replace(/[\r\n]/g, '');

    const base64Regex = /^[A-Za-z0-9+/]+={0,2}$/;

    if (!base64Regex.test(cleanImage) ||
        cleanImage.length % 4 !== 0) {
        return { error: 'Field image harus berupa Base64 yang valid', image: null };
    }
    const buffer = Buffer.from(cleanImage, 'base64');
    if (buffer.length > MAX_IMAGE_SIZE) {
        return { error: 'Ukuran image maksimal 2 MB', image: null };
    }

    return { error: null, image: cleanImage };
}

    // GET ambil semua products
async function index(req, res) {
    try {
        const products = await productModel.getAllProducts();
        res.status(200).json({
            message: 'Berhasil mengambil data produk',
            data: products
        });
    } catch (error) {
        res.status(500).json({ 
            message: 'Gagal mengambil data produk',
            error: error.message 
        });
    }
}

    // GET berdasarkan ID
async function show(req, res) {
    try {
        const id = Number(req.params.id);
        if (!Number.isInteger(id) || id <= 0) {
            return res.status(400).json({ message: 'ID produk tidak valid' });
        }
        const product = await productModel.getProductById(id);
        if (!product) {
            return res.status(404).json({ message: 'Produk tidak ditemukan' });
        }
        res.status(200).json({
            message: 'Berhasil mengambil detail produk',
            data: product
        });
    } catch (error) {
        res.status(500).json({
            message: 'Gagal mengambil detail produk',
            error: error.message
        });
    }
}    

    // POST name, price, stock wajib
async function createProduct(req, res) {
    try {
        const { name, description, price, stock } = req.body;
        let { image } = req.body;

        if (!name || price === undefined || stock === undefined) {
            return res.status(400).json({
                message: 'Field name, price, dan stock wajib diisi'
            });
        }
        const imageResult = validateBase64Image(image);
        if (imageResult.error) {
            return res.status(400).json({
                message: imageResult.error
            });
        }
        image = imageResult.image;
        const product = await productModel.createProduct({
            name,
            description: description || null,
            price,
            stock,
            image
        });

        res.status(201).json({
            message: 'Berhasil menambah data produk',
            data: product
        });

    } catch (error) {
        res.status(500).json({
            message: 'Gagal menambah data produk',
            error: error.message
        });
    }
}


    // PUT name, price, stock wajib
async function updateProduct(req, res) {
    try {
        const id = Number(req.params.id);
        if (!Number.isInteger(id) || id <= 0) {
            return res.status(400).json({ message: 'ID produk tidak valid' });
        }
        const { name, description, price, stock } = req.body;
        let { image } = req.body;
        if (!name || price === undefined || stock === undefined) {
            return res.status(400).json({ message: 'Field name, price, dan stock wajib diisi' });
        }
        const existing = await productModel.getProductById(id);
        if (!existing) {
            return res.status(404).json({ message: 'Produk tidak ditemukan' });
        }
        const imageResult = validateBase64Image(image);

        if (imageResult.error) {
            return res.status(400).json({
                message: imageResult.error
            });
        }
        image = imageResult.image;
        const product = await productModel.updateProduct(id, { name, description: description || null, price, stock,image });
        res.status(200).json({
            message: 'Berhasil memperbarui data produk',
            data: product
        });
    } catch (error) {
        res.status(500).json({
            message: 'Gagal memperbarui data produk',
            error: error.message
        });
    }
}

// DELETE hapus produk
async function destroy(req, res) {
    try {
        const id = Number(req.params.id);
        if (!Number.isInteger(id) || id <= 0) {
            return res.status(400).json({ message: 'ID produk tidak valid' });
        }
        const deleted = await productModel.deleteProduct(id);
        if (!deleted) {
            return res.status(404).json({ message: 'Produk tidak ditemukan' });
        }
        res.status(200).json({ message: 'Berhasil menghapus data produk' });
    } catch (error) {
        res.status(500).json({
            message: 'Gagal menghapus data produk',
            error: error.message
        });
    }
}

module.exports = {
    index,
    show,
    createProduct,
    updateProduct,
    destroy
};